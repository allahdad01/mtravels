# Finance Role — Complete Documentation

## Overview

The **Finance** role is a branch-level role focused on financial operations. It shares the same `admin/` directory as Branch Admin but has restricted access to destructive management actions (cannot delete records). The Finance role gets a unique **Finance Wallet** (Finance Tracker) feature not available to other roles.

## RBAC Position

```
Branch Admin (full access)
    └── Finance  (financial modules, read-only for destructive actions)
```

## Session & Authorization

| Mechanism | Detail |
|-----------|--------|
| `staffCanSeeMenu()` | Returns `true` — sees full navigation (same menu as admin) |
| `allowed_admin_roles` | `['admin', 'tenant_super_admin', 'finance', 'sales', 'umrah', 'staff']` |
| `$isAdmin` flag | NOT set — `$isAdmin` is only for `admin` role |
| Dashboard staff redirect | NOT redirected (only `staff` role is) |

## Unique Features

### Finance Wallet (Finance Tracker)
**File:** `admin/finance_tracker.php`

This is the only menu item gated specifically to the `finance` role:
```php
<?php if ($user['role'] === 'finance'): ?>
<li class="nav-item <?= navActive('finance_tracker.php') ?>">
    <a href="finance_tracker.php" class="nav-link">
        <span class="pcoded-micon"><i class="fas fa-chart-line"></i></span>
        <span class="pcoded-mtext">Finance Wallet</span>
    </a>
</li>
<?php endif; ?>
```

Full details of this module would need exploration of `admin/finance_tracker.php`.

## Dashboard (`admin/dashboard.php`)

| Widget | Visible to Finance? |
|--------|-------------------|
| KPI Cards (total revenue, bookings, clients) | Yes |
| Today's Attendance & Recent Bookings | Yes |
| Monthly Booking Trend Chart | Yes |
| Supplier & Client Alerts | Yes |
| Financial Wealth Distribution Chart | **No** (admin only) |
| Performance Overview / Sales Cards | **No** (admin only) |
| Top Performers | **No** (admin only via `$user['role']==='admin'`) |
| Top Performers + Client Debts | **Yes** (visible via `in_array($user['role'],['admin','finance'])`) |

## Feature Access by Module

### What Finance CAN do (same as Admin)
| Module | Access Level |
|--------|-------------|
| **Accounts** (`accounts.php`) | View all accounts, view account transactions |
| **Debtors** (`debtors.php`) | View debtors, add transactions, print receipts, **cannot delete** |
| **Creditors** (`creditors.php`) | View creditors, add transactions, print receipts, **cannot delete** |
| **Sarafi** (`sarafi.php`) | Full CRUD |
| **Expenses** (`expense_management.php`) | Full CRUD |
| **Budget Allocation** (`budget_allocations.php`) | Full CRUD |
| **Global Budget Allocation** | Full CRUD |
| **Additional Payments** | Full CRUD |
| **JV Payments** | Full CRUD |
| **Reports** (`report.php`) | Full (all categories) |
| **HR Dashboard** | Full |
| **Employee Management** | View/Create/Edit (typically) |
| **Salary Management** | Full (process payments, view history) |
| **Suppliers** | View/Create/Edit |
| **Clients** | View/Create/Edit |
| **Search** | Full |
| **Activity Log** | Full |
| **Support Tickets** | Full |

### What Finance CANNOT do (gated behind `$isAdmin`)
| Action | Page | Detail |
|--------|------|--------|
| Delete accounts | `accounts.php` | Main account management hidden |
| Delete debtors | `debtors.php` | Delete debtor button hidden |
| Delete debtor transactions | `debtors.php` | Delete transaction button hidden |
| Delete creditors | `creditors.php` | Delete creditor button hidden |
| Delete creditor transactions | `creditors.php` | Delete transaction button hidden |
| Toggle supplier status | `accounts.php` | Activate/deactivate supplier buttons hidden |
| Quarterly Tax Report | `report.php` | Submenu item hidden |
| View Main Account Balances | `accounts.php` | Quick-stats bar with main USD/AFS hidden |
| View Internal/Main Accounts section | `accounts.php` | Section hidden |

### What Finance CANNOT do (general)
| Action | Reason |
|--------|--------|
| Manage users (add/edit/delete) | User management is admin-level |
| Modify attendance settings | Typically admin-level |
| Delete records (employees, etc.) | Typically admin-level |
| See Financial Wealth Chart | Admin-only dashboard widget |
| See Performance Overview | Admin-only dashboard widget |

## Finance-Specific Menu Item

| Menu Item | File | Role Gate |
|-----------|------|-----------|
| **Finance Wallet** | `finance_tracker.php` | `$user['role'] === 'finance'` |

## Shared Directory

Finance operates from the `admin/` directory (shared with admin, sales, umrah, staff). The `$isAdmin` checks throughout the codebase prevent Finance from performing destructive operations while allowing full read and standard create/edit workflow access.

## Conclusion

The **Finance** role is designed for accountants and financial staff within a branch. It provides:
- **Full visibility** into all financial data (accounts, debtors, creditors, expenses, sarafi)
- **Operational access** to create/edit transactions and payments
- **No destructive power** (cannot delete records, accounts, or manage users)
- **Unique tool**: Finance Wallet for dedicated financial tracking
- Restricted from admin-only dashboard widgets and quarterly tax reporting
