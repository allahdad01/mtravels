# Umrah Staff Role — Complete Documentation

## Overview

The **Umrah** role is a specialized branch-level role focused exclusively on umrah operations. It shares the `admin/` directory with other roles. Umrah staff can manage umrah bookings, services, refunds, and date changes, and have a **specialized report category** in the Reports module filtered to umrah data only.

## RBAC Position

```
Branch Admin (full access)
    └── Umrah  (umrah operations, umrah-specific reports)
```

## Session & Authorization

| Mechanism | Detail |
|-----------|--------|
| `staffCanSeeMenu()` | Returns `true` — sees full navigation |
| `allowed_admin_roles` | `['admin', 'tenant_super_admin', 'finance', 'sales', 'umrah', 'staff']` |
| Role-specific features | Report category default changes to Umrah |
| Dashboard staff redirect | NOT redirected (only `staff` role is) |

## Dashboard (`admin/dashboard.php`)

| Widget | Visible to Umrah? |
|--------|------------------|
| KPI Cards | Yes |
| Today's Attendance & Recent Bookings | Yes |
| Monthly Booking Trend Chart | Yes |
| Supplier & Client Alerts | Yes |
| All admin-only widgets | **No** (admin-only) |

## Feature Access by Module

### What Umrah CAN do
| Module | Access Level |
|--------|-------------|
| **Umrah Bookings** (`umrah.php`, `umrah_detail.php`) | Full CRUD |
| **Umrah Services** (`umrah_services.php`) | Full CRUD |
| **Umrah Refunds** (`umrah_refunds.php`) | Full CRUD |
| **Umrah Date Changes** (`umrah_date_changes.php`) | Full CRUD |
| **Clients** | Full CRUD |
| **Reports** (`report.php`) | Limited — **defaults to Umrah categories only** |
| **Search** | Full |
| **Support Tickets** | Create/view own tickets |
| **Tutorials** | View |
| **2FA** | Configure |
| **Activity Log** | View |

### Umrah-Specific Report Behavior

**File:** `admin/report.php:194`

```php
<?php if ($_SESSION['role'] === 'umrah'): ?>
    <option value="umrah">🕌 Umrah</option>
    <option value="umrah_refund">↩️ Umrah Refund</option>
<?php else: ?>
    <option value="ticket">🎫 Ticket</option>
    ...
<?php endif; ?>
```

When the logged-in user has the `umrah` role, the report category dropdown **only shows Umrah-related options** (Umrah Bookings and Umrah Refunds). All other report categories (tickets, hotels, visas) are hidden.

### What Umrah CANNOT do
| Action | Reason |
|--------|--------|
| Ticket bookings | Not umrah-related |
| Hotel bookings | Not umrah-related |
| Visa applications | Not umrah-related |
| Accounts / Debtors / Creditors | Financial role |
| Sarafi | Financial role |
| Expense Management | Financial role |
| Additional Payments | Financial role |
| JV Payments | Financial role |
| HR Management | Administrative |
| Salary Management | Administrative |
| User Management | Administrative |
| Branch Settings | Administrative |
| Manage Letters (Maktobs) | Operations/Admin |
| Assets | Operations/Admin |
| Chat Settings | Communication admin |
| Non-umrah reports | Report categories restricted |

## Shared Directory

Umrah operates from the `admin/` directory. The main distinguishing feature is the **report category restriction** — Umrah staff can only generate reports for Umrah-related data, not for tickets, hotels, or visas.

## Menu Items Visible to Umrah

- **Travel & Services** — Only **Umrah Management** submenu is relevant (Tickets/Hotels/Visa menus may appear if plan features are enabled, but actual page-level access may be restricted)
- **Finance & Accounting** — Hidden (no financial modules)
- **People & HR** — Typically hidden
- **Operations** — Clients and Search visible
- **Communication** — Depends on plan features
- **Security** — Activity Log visible
- **Support** — Support Tickets and Tutorials visible

## Conclusion

The **Umrah** role is a highly specialized role designed for staff who handle only umrah operations. It provides:
- **Full control** over umrah bookings, services, refunds, and date changes
- **Umrah-only reports** — the report module defaults to umrah categories
- **Client management** for umrah-related customer records
- **No access** to other travel services (tickets, hotels, visas)
- **No financial or administrative access**
