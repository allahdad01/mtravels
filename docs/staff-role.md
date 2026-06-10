# Staff Role — Complete Documentation

## Overview

The **Staff** role is the most restricted branch-level role. It is designed for employees who only need to manage their own attendance (check-in/check-out) and view their salary payments. Staff have a **completely separate simplified dashboard** and cannot see any administrative pages, financial data, or management interfaces.

## RBAC Position

```
Branch Admin (full access)
    └── Staff  (self-service only: attendance + payments)
```

## Session & Authorization

| Mechanism | Detail |
|-----------|--------|
| `staffCanSeeMenu()` | Returns **`false`** — most navigation is hidden |
| `allowed_admin_roles` | `['admin', 'tenant_super_admin', 'finance', 'sales', 'umrah', 'staff']` — allows URL access |
| Dashboard redirect | Line 26 of `dashboard.php`: **redirected to `staff_dashboard_view.php`** |

## Critical Code Gate

**File:** `includes/auth_check.php:134`

```php
function staffCanSeeMenu(string $userRole): bool {
    return $userRole !== 'staff';
}
```

This function is called throughout `nav_items.php` to conditionally render navigation items. Since it returns `false` for `staff`, **most menu items are hidden**.

## Dashboard (`admin/dashboard.php`)

**File:** `admin/dashboard.php:26-31`

The staff role is **immediately redirected** to a separate simplified dashboard:

```php
if ($_SESSION['role'] === 'staff') {
    require_once '../api/dashboard/staff_dashboard.php';
    include '../includes/header.php';
    include '../admin/staff_dashboard_view.php';
    exit();
}
```

### Staff Dashboard Data (`api/dashboard/staff_dashboard.php`)

| Data Point | Source |
|------------|--------|
| Staff name (first name) | `users.name` |
| Current month attendance | `attendance` table — total, present, absent, late/half-day counts |
| Attendance rate % | Calculated: `(present / total) * 100` |
| Recent payments (last 5) | `salary_payments` table — description, amount, date, type |
| Total paid this month | `SUM(amount)` from `salary_payments` |

### Staff Dashboard View (`admin/staff_dashboard_view.php`)

A simplified UI showing:
- **Greeting** with staff name
- **Attendance Summary Cards** — Present, Absent, Leave, Attendance Rate
- **Recent Payments Table** — Last 5 salary payments with date, description, amount, status

## Navigation Menu

Only the following items are visible to Staff (all other menus are gated by `staffCanSeeMenu()`):

| Menu Item | File | Source |
|-----------|------|--------|
| **My Attendance** | `attendance.php` | `nav_items.php:354` — dedicated `else` branch for staff |
| **My Payments** | `salary_payments.php` | `nav_items.php:386` — dedicated `else` branch for staff |
| **2FA** | `totp_setup.php` | `nav_items.php:511` — always visible |
| **Tutorials** | `tutorial.php` | `nav_items.php:554` — always visible |

### Nav Items Hidden from Staff (gated by `staffCanSeeMenu()`)

All the following sections are **completely hidden** from Staff:

| Section | Items Hidden |
|---------|-------------|
| **Travel & Services** | Tickets (all sub-items), Hotels (all sub-items), Umrah (all sub-items), Visa (all sub-items) |
| **Finance & Accounting** | Accounts, Debtors, Creditors, Sarafi, Expenses (all sub-items), Additional Payments, JV Payments, Reports |
| **People & HR** | HR Dashboard, Employee Management, Performance Reviews, Manage Attendance (admin view), Attendance Settings, Salary Management, Process Payment |
| **Operations** | Manage Letters, Assets, Excel Import, Search, Suppliers, Clients |
| **Communication** | Chat, Chat Settings, Tenant/Branch Peering, Email Analytics |
| **Security & Monitoring** | Activity Log |
| **Support** | Support Tickets (hidden), Tutorials (visible) |

## Feature Access

### What Staff CAN do
| Module | Access Level | File |
|--------|-------------|------|
| **View own attendance** | View own check-in/out status | `attendance.php` |
| **View own payments** | View salary payment history | `salary_payments.php` |
| **2FA** | Set up two-factor authentication | `../totp_setup.php` |
| **Tutorials** | View tutorials | `tutorial.php` |

### What Staff CANNOT do
| Category | Details |
|----------|---------|
| **Travel Services** | Cannot book tickets, hotels, visas, or umrah |
| **Financial Data** | Cannot view accounts, debtors, creditors, expenses, sarafi |
| **HR Management** | Cannot manage employees, attendance (of others), or settings |
| **Salary Management** | Cannot process payments or manage others' salaries |
| **Client/Supplier Data** | Cannot view or manage client or supplier records |
| **Operations** | Cannot manage letters, assets, imports, or search |
| **Communication** | Cannot access chat, email analytics |
| **Reporting** | Cannot generate reports of any kind |
| **Activity Log** | Cannot view system activity logs |
| **Support Tickets** | Cannot create or view support tickets |
| **Dashboard admin widgets** | Not applicable (redirected to staff dashboard) |

## Security Note

While `session_check.php` allows `staff` role to access the `admin/` directory (line 108), the actual page-level access is limited because:
1. Staff are immediately redirected to `staff_dashboard_view.php` at `dashboard.php:26`
2. The navigation menu only shows 4 items (Attendance, Payments, 2FA, Tutorials)
3. Individual admin pages may have additional server-side role checks

## Conclusion

The **Staff** role is strictly a **self-service employee role**. It provides:
- **Attendance tracking** — check in/out and view monthly attendance summary
- **Payment history** — view past salary payments and total paid for the month
- **Account security** — configure 2FA
- **Learning resources** — access tutorials
- **No administrative, financial, or operational capabilities whatsoever**
