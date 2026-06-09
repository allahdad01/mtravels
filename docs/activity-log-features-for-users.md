# 📋 M.Travels Activity Log Module — Features

A complete audit trail of every action taken in your system — who did what, when, where, and what changed — with powerful filters and bulk cleanup capabilities.

---

## 🔍 What's Recorded

Every significant action across all modules is automatically logged:

- **Records Created** — When anyone adds a booking, client, supplier, payment, or any record
- **Records Updated** — Every change made with before/after snapshots
- **Records Deleted** — When records are removed
- **Financial Actions** — Funds, bonuses, payments received, refunds
- **User Activity** — Logins, logouts, account changes
- **Settings Changes** — Platform configuration updates
- **Security Events** — Failed logins, locked accounts, alerts

---

## 🎯 Powerful Filters

Find exactly what you're looking for:

| Filter | What It Does |
|--------|-------------|
| **Date Range** | View logs from a specific period (default: last 30 days) |
| **User** | Filter by who performed the action |
| **Action Type** | Show only adds, updates, deletions, etc. |
| **Table** | Focus on a specific module (tickets, visas, clients, etc.) |
| **Live Search** | Type any keyword to instantly filter results on screen |

The filter bar auto-opens when filters are active and collapses when not needed.

---

## 📊 Detailed View

Click any log entry to see full details in a modal:
- **User Info** — Who did it and from what IP address and device
- **Old Values** — What the record looked like before the change (JSON)
- **New Values** — What the record looks like after the change (JSON)
- **Timestamps** — Exactly when it happened

---

## 🗑️ Log Cleanup

Two ways to manage log volume:

| Option | How It Works |
|--------|-------------|
| **Delete Single Entry** | Remove one specific log entry |
| **Bulk Delete** | Remove all log entries before a selected date |

Both are protected by confirmation and require your session to stay authenticated.

---

## 👑 Super Admin Panel

Super admins have their own activity log viewer with:
- Same filters plus branch name display
- Stats tiles: Total Logs, Active Users, Active Days
- Tabbed detail modal: Summary, Old Values, New Values, Technical
- 25 records per page

---

## 🔐 Access Control

| Role | What They Can See |
|------|------------------|
| **Admin** | Full access — view, filter, delete logs |
| **Super Admin** | Cross-branch activity log viewer |
| **Other Roles** | No access |

---

## 📱 Language Support

Available in all 3 languages: English, Dari (فارسی), and Pashto (پښتو).

---

## 💻 What You Get

| Resource | Count |
|----------|-------|
| Admin Activity Log Page | 1 |
| Super Admin Page | 1 |
| Helper Class | 1 (`ActivityLogger`, 339 lines) |
| Database Table | 1 (`activity_log`) |
| Recorded Tables | ~35 |
| Recorded Action Types | ~30 |
| Records Per Page | 50 (admin), 25 (super admin) |
| Languages | 3 |

---

*Know everything that happens in your system — complete transparency and accountability.*
