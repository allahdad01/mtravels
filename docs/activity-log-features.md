# Activity Log Module — Complete Feature Documentation

## Overview

Multi-tenant, branch-scoped audit trail capturing all user actions across every module with JSON before/after snapshots, IP tracking, user agent logging, multi-condition filtering, single/bulk deletion, and a dedicated `ActivityLogger` helper class.

---

## 1. Page

**File:** `admin/activity_log.php` (1,084 lines)

| Aspect | Detail |
|--------|--------|
| **Role** | Admin-only (`$_SESSION['role'] !== 'admin'` redirects) |
| **Pagination** | 50 records per page, ordered by `created_at DESC` |
| **Sort** | URL-preserving pagination links (first/prev/next/last) |

---

## 2. Filters

| Filter | Input Type | Default |
|--------|-----------|---------|
| **Date From** | `<input type="date">` | 30 days ago |
| **Date To** | `<input type="date">` | Today |
| **User** | `<select>` from `users` table | All Users |
| **Action** | `<select>` of distinct `action` values | All Actions |
| **Table Name** | `<select>` of distinct `table_name` values | All Tables |
| **Live Text Search** | Client-side JS — filters rows in-browser by any text content | — |

Filter bar is collapsible; auto-opens when any filter parameter is present in URL.

---

## 3. Database Table

### `activity_log`
| Column | Type | Detail |
|--------|------|--------|
| id | bigint(20) unsigned PK | Auto-increment |
| tenant_id | int(11) FK | CASCADE on delete |
| branch_id | int(11) FK | CASCADE on delete |
| user_id | int(11) | Indexed |
| action | varchar(50) | Indexed — `add`, `update`, `delete`, `login`, `logout`, `fund`, `bonus`, etc. |
| table_name | varchar(100) | Indexed — `ticket_bookings`, `clients`, `suppliers`, etc. |
| record_id | int(11) | PK of affected record |
| old_values | text | JSON snapshot before change |
| new_values | text | JSON snapshot after change |
| ip_address | varchar(45) | IPv6-compatible |
| user_agent | varchar(255) | Browser/device string |
| created_at | timestamp | |

---

## 4. Actions Logged (~30 types)

| Action | Source |
|--------|--------|
| `add`, `create`, `insert` | Record creation across all modules |
| `update`, `edit` | Record modifications |
| `delete`, `remove` | Record deletion |
| `fund` | Account/supplier funding |
| `bonus` | Supplier bonus |
| `login`, `logout` | User sessions |
| `login_failed`, `login_success` | Auth events |
| `user_created`, `user_updated`, `user_deleted` | User management |
| `user_locked`, `user_unlocked` | Account locking |
| `payment_received`, `payment_failed`, `payment_refunded` | Payment events |
| `settings_changed`, `config_changed` | Platform settings |
| `subscription_changed` | Subscription lifecycle |
| `data_exported`, `data_imported`, `data_deleted` | Data operations |
| `security_alert` | Security events |

---

## 5. Logged Tables (~35)

`ticket_bookings`, `ticket_reservations`, `refunded_tickets`, `date_change_tickets`, `ticket_weights`, `visa_applications`, `visa_refunds`, `hotel_bookings`, `hotel_refunds`, `umrah_bookings`, `umrah_members`, `umrah_refunds`, `umrah_transactions`, `clients`, `client_transactions`, `suppliers`, `supplier_transactions`, `main_account`, `main_account_transactions`, `expense_categories`, `expenses`, `debtor_transactions`, `creditor_transactions`, `additional_payments`, `jv_payments`, `salary`, `assets`, `users`, `branches`, `platform_settings`, `subscription_payments`, `payment_sessions`

---

## 6. Delete Operations

| Operation | Trigger | Detail |
|-----------|---------|--------|
| **Single Delete** | POST `delete_log` + `log_id` | Deletes one row, scoped by tenant + branch |
| **Bulk Delete** | POST `bulk_delete` + `delete_before_date` | Deletes all rows before date, scoped by tenant + branch |

Both require CSRF token validation. No restore/undo capability.

---

## 7. UI Features

| Feature | Detail |
|---------|--------|
| **Table** | Columns: Date/Time, User, Action, Table, Record ID, IP Address, Details |
| **Detail Modal** | Click any row — shows full data grid + Old Values / New Values JSON panels |
| **Live Search** | Client-side text filter across all rows |
| **Filter Bar** | Collapsible, auto-opens when filters active |
| **Escape Key** | Closes open modal |

---

## 8. ActivityLogger Class

**File:** `includes/ActivityLogger.php` (339 lines)

| Method | Purpose |
|--------|---------|
| `log()` | Generic insert |
| `logUserCreated()` | User creation (redacts password) |
| `logUserDeleted()` | User deletion (redacts password) |
| `logUserUpdated()` | User updates |
| `logPaymentReceived()` | Payment success |
| `logPaymentFailed()` | Payment failure |
| `logSettingsChanged()` | Settings changes (redacts SMTP password, API keys) |
| `logDataExported()` | Data exports |
| `logSecurityAlert()` | Security events |
| `getActivityLogs()` | Retrieves logs with filters (action, user_id, date range, limit) |
| `getActivityStats()` | Action counts, distinct users, last occurrence for N days |

---

## 9. Super Admin Panel

**Page:** `tenant_super_admin/activity_logs.php` (559 lines)

| Feature | Detail |
|---------|--------|
| **Filters** | Search text, User, Action, Table |
| **Stats** | Total Logs, Active Users, Active Days |
| **Pagination** | 25 records per page |
| **Modal** | Summary + Old Values + New Values + Technical tabs |
| **Branch** | Shows `branch_name` via JOIN |

---

## 10. Navigation & Feature Gating

| Check | Detail |
|-------|--------|
| **Menu visibility** | `staffCanSeeMenu()` — hidden from `staff` role |
| **Feature gate** | `activity_logging` under `reporting_analytics` group in pricing-helper.php |
| **Page access** | Hard-coded `$_SESSION['role'] !== 'admin'` — admin only |

---

## 11. Languages

| Language | File |
|----------|------|
| English | `includes/languages/en/common.php` |
| Dari/Farsi | `includes/languages/fa/common.php` |
| Pashto | `includes/languages/ps/common.php` |

---

## 12. File Map

```
admin/
  activity_log.php                    # Main page (1,084 lines)
tenant_super_admin/
  activity_logs.php                   # Super admin panel (559 lines)
includes/
  ActivityLogger.php                  # Structured logging class (339 lines)
  db_security.php                     # Input validation
  InputValidator.php                  # Input sanitization
  security.php                        # Auth enforcement
  nav_items.php                       # Menu entry
  pricing-helper.php                  # Feature gate definition
  languages/en/common.php             # Translation keys
  languages/fa/common.php             # Translation keys
  languages/ps/common.php             # Translation keys
```
