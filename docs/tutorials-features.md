# Tutorial Module — Complete Feature Documentation

## Overview

Dual-system tutorial module: a database-driven system (with full CRUD, role/per-page filtering, chapter timestamps, YouTube/Vimeo support, contextual in-app help) alongside legacy hardcoded tutorials on Dashboard and Accounts pages.

---

## 1. Core Pages

| Page | Purpose | Access |
|------|---------|--------|
| `admin/tutorial.php` | Public tutorial catalog — filtered by role, search, category filter, video player with chapters | All admin roles |
| `admin/tutorial_manager.php` | Legacy reference — 35 hardcoded tutorials grouped by category (static, for manual Vimeo ID assignment) | admin, finance, sales, umrah |
| `super_admin/manage_tutorials.php` | Full CRUD — DataTable with edit/delete, modal form with all fields | Super admin only |

---

## 2. API Endpoints (`api/tutorials/`)

| Endpoint | Method | Auth | Purpose |
|----------|--------|------|---------|
| `list.php` | GET | All roles | List tutorials — supports `?page=filename.php` (contextual) and `?all=1` (super admin). Role-filtered server-side via `roles` JSON column. |
| `get.php` | GET | Super admin | Single tutorial by ID |
| `add.php` | POST | Super admin | Create tutorial (CSRF protected) |
| `update.php` | POST | Super admin | Update tutorial (CSRF protected) |
| `delete.php` | POST | Super admin | Delete tutorial (CSRF protected, JSON body) |
| `thumb.php` | GET | All roles | Video thumbnail URL — YouTube direct, Vimeo via oEmbed with 24-hour file cache |

---

## 3. Database Table

### `tutorials`
| Column | Type | Detail |
|--------|------|--------|
| id | int(11) PK | Auto-increment |
| title | varchar(255) | |
| description | text | |
| category | varchar(100) | e.g. basics, tickets, clients, finance, hr, services, reports, settings, security |
| page | varchar(255) | Associated PHP filename for contextual help (e.g. `dashboard.php`) |
| video_type | enum('youtube','vimeo') | Video platform |
| video_id | varchar(255) | Video ID |
| chapters | longtext | JSON array `[{label, time, seconds}]` |
| duration | varchar(20) | e.g. `5:00` |
| level | enum('Beginner','Intermediate','Advanced') | |
| roles | longtext | JSON array `["all"]` or `["admin","finance",...]` |
| sort_order | int(11) | Display ordering |
| status | tinyint(1) | 1=active, 0=inactive |
| created_by | int(11) | FK → `users` |

---

## 4. In-App Contextual Help System

**File:** `includes/header.php` (lines 989–1975)

| Component | Detail |
|-----------|--------|
| **Help Button** | `?` icon in admin header on every page |
| **Fetch** | `loadPageTutorials()` — calls `api/tutorials/list.php?page=currentFile.php` |
| **Render** | Clickable items with title, description, chapters |
| **Player Modal** | `#helpVideoModal` overlay with YouTube/Vimeo iframe |
| **Chapters** | `renderHelpChapters()` — seek buttons call YouTube/Vimeo Player APIs |
| **Close** | `closeHelpVideo()` — stops video, hides modal |

Also duplicated in `tenant_super_admin/header.php` (lines 2195–2219).

---

## 5. Module-Specific Tutorial Modals (Hardcoded)

| Module | File | Count | Topics |
|--------|------|-------|--------|
| **Dashboard** | `admin/dashboard.php` | 3 | Overview, Sales Cards & Dues, Departures & Top Performers |
| **Accounts** | `admin/accounts.php` | 6 | View Balances, Search/Filter, Add Funds, Withdraw, Transaction History, Manage Status |

These use split-panel modals: video player (left) + tutorial list (right) with click-to-select.

---

## 6. Legacy Tutorial Manager

**File:** `admin/tutorial_manager.php` — 35 hardcoded tutorials in 10 categories:

| Category | Count | Topics |
|----------|-------|--------|
| Basics | 2 | Dashboard Overview, Navigating Admin Panel |
| Tickets | 4 | Bookings, Viewing/Filtering, Refunds/Date Changes, Weights/Reservations |
| Clients | 3 | Adding, Managing, Credits/Debtors |
| Suppliers | 3 | Adding, Accounts/Transactions, Sarafi |
| Finance | 4 | Accounts, Payments, JV, Reports |
| HR | 4 | Employees, Salary, Payroll, Performance |
| Services | 4 | Umrah, Visa, Hotels, Additional Services |
| Reports | 4 | Dashboard, Monthly, Email Analytics, Activity Logs |
| Settings | 3 | Users, System, Chat |
| Security | 4 | Security, Import/Export, Compliance, API Docs |

---

## 7. Tutorial Image Assets

21 module directories under `uploads/tutorials/`:

| Directory | PNGs |
|-----------|------|
| `accounts_tutorials/` | 10 |
| `additional_payment_tutorials/` | 3 |
| `assets_tutorials/` | 2 |
| `budget_tutorials/` | 5 |
| `client_tutorials/` | 2 |
| `creditor_tutorials/` | 4 |
| `date_change_tutorials/` | 3 |
| `debtor_tutorials/` | 4 |
| `expense_tutorials/` | 3 |
| `hotel_tutorials/` | 4 |
| `jv_tutorials/` | 2 |
| `refund_ticket_tutorials/` | 4 |
| `salary_tutorials/` | 3 |
| `sarafi_tutorials/` | 6 |
| `supplier_tutorials/` | 2 |
| `system_tutorials/` | 9 |
| `ticket_tutorials/` | 4 |
| `ticket_weight_tutorials/` | 3 |
| `umrah_tutorials/` | 8 |
| `user_tutorials/` | 3 |
| `visa_tutorials/` | 5 |

---

## 8. Role & Permission Model

| Page / API | Allowed Roles |
|-----------|---------------|
| `admin/tutorial.php` | admin, finance, sales, umrah, staff, tenant_super_admin |
| `api/tutorials/list.php` | admin, finance, sales, umrah, staff, tenant_super_admin, super_admin |
| `api/tutorials/thumb.php` | Same as list |
| CRUD APIs (add/update/delete/get) | Super admin only |
| `super_admin/manage_tutorials.php` | Super admin only |

**Feature gate:** Not explicitly gated in pricing — available to all tenants.

**Menu visibility:**
- Admin sidebar → `nav_items.php` line 553 (all roles)
- Super admin sidebar → `header_super_admin.php` (super admin only)
- Tenant super admin → `tenant_super_admin/header.php`

---

## 9. Languages

| Language | File |
|----------|------|
| English | `includes/languages/en/common.php` (~30+ keys) |
| Dari/Farsi | `includes/languages/fa/common.php` |
| Pashto | `includes/languages/ps/common.php` |

---

## 10. File Map

```
admin/
  tutorial.php                          # Tutorial catalog page
  tutorial_manager.php                  # Legacy static reference (35 tutorials)
  dashboard.php                         # Hardcoded 3-tutorial modal
  accounts.php                          # Hardcoded 6-tutorial modal
super_admin/
  manage_tutorials.php                  # Full CRUD management
api/tutorials/
  list.php                              # List (role-filtered, page-aware)
  get.php                               # Single tutorial (super admin)
  add.php                               # Create (super admin)
  update.php                            # Update (super admin)
  delete.php                            # Delete (super admin)
  thumb.php                             # Thumbnail proxy with caching
includes/
  header.php                            # Contextual help menu + video modal
  header_super_admin.php                # Super admin help menu
  nav_items.php                         # Admin sidebar entry
database_migration_tutorials.sql        # tutorials table schema
uploads/tutorials/                      # 21 module directories of PNGs
```
