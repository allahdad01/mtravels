# Sales Role — Complete Documentation

## Overview

The **Sales** role is a branch-level role focused on customer-facing travel services. It shares the `admin/` directory with other branch roles. Sales staff can book tickets, hotels, visas, and umrah packages, manage clients, and view reports — but cannot perform financial management or administrative actions.

## RBAC Position

```
Branch Admin (full access)
    └── Sales  (travel services, clients, reports)
```

## Session & Authorization

| Mechanism | Detail |
|-----------|--------|
| `staffCanSeeMenu()` | Returns `true` — sees full navigation (same menu as admin) |
| `allowed_admin_roles` | `['admin', 'tenant_super_admin', 'finance', 'sales', 'umrah', 'staff']` |
| Role-specific dashboard items | None — uses the default dashboard without admin-only widgets |
| Dashboard staff redirect | NOT redirected (only `staff` role is) |

## Dashboard (`admin/dashboard.php`)

| Widget | Visible to Sales? |
|--------|------------------|
| KPI Cards (total revenue, bookings, clients) | Yes |
| Today's Attendance & Recent Bookings | Yes |
| Monthly Booking Trend Chart | Yes |
| Supplier & Client Alerts | Yes |
| Top Performers + Client Debts | **No** (admin+finance only) |
| Financial Wealth Distribution Chart | **No** (admin only) |
| Performance Overview / Sales Cards | **No** (admin only) |
| Top Performers | **No** (admin only) |

## Feature Access by Module

### What Sales CAN do
| Module | Access Level |
|--------|-------------|
| **Ticket Bookings** | Full CRUD (book tickets, view, manage) |
| **Ticket Reservations** | Full CRUD |
| **Refunded Tickets** | Full CRUD |
| **Date Change Tickets** | Full CRUD |
| **Ticket Weights** | Full CRUD |
| **Hotel Bookings** | Full CRUD |
| **Hotel Refunds** | Full CRUD |
| **Umrah Bookings** | Full CRUD |
| **Umrah Refunds** | Full CRUD |
| **Umrah Date Changes** | Full CRUD |
| **Visa Applications** | Full CRUD |
| **Visa Refunds** | Full CRUD |
| **Clients** | Full CRUD (create/manage client records) |
| **Suppliers** | View/Create/Edit |
| **Search** | Full |
| **Reports** (`report.php`) | Full (all categories) |
| **Activity Log** | View |
| **Support Tickets** | Create/view own tickets |
| **Tutorials** | View |
| **2FA** | Configure |

### What Sales CANNOT do
| Action | Reason |
|--------|--------|
| View/Manage Accounts (`accounts.php`) | Financial role |
| Manage Debtors (`debtors.php`) | Financial role |
| Manage Creditors (`creditors.php`) | Financial role |
| Sarafi (`sarafi.php`) | Financial role |
| Expense Management | Financial role |
| Budget Allocation | Financial role |
| Additional Payments | Financial role |
| JV Payments | Financial role |
| HR Management | Administrative |
| Salary Management | Administrative |
| Manage Attendance | Administrative |
| User Management | Administrative |
| Manage Letters (Maktobs) | Operations/Admin |
| Assets | Operations/Admin |
| Chat Settings | Communication admin |
| Quarterly Tax Report | Admin only |
| Finance Wallet | Finance only |

## Shared Directory

Sales operates from the `admin/` directory. Menu visibility is controlled by `staffCanSeeMenu()` (returns `true`) and `hasFeature()` (plan-level gating). Unlike Finance, there are no `$isAdmin`-related checks affecting Sales because the Sales role doesn't access pages with destructive financial actions.

## Menu Items Visible to Sales

Sales sees the full navigation menu, but many items are not displayed because they are gated by `hasFeature()` checks. In practice:
- **Travel & Services** section — fully visible (tickets, hotels, umrah, visa)
- **Finance & Accounting** section — **hidden** (accounts, debtors, creditors, sarafi, expenses, payments, reports are gated by `staffCanSeeMenu()` which returns true, but individual pages may restrict access; reports are visible)
- **People & HR** section — **typically hidden** (HR/Salary require `hasFeature('salary')`)
- **Operations** section — Clients and Search visible; Letters and Assets depend on plan features
- **Communication** — Chat/Chat Settings depend on plan features
- **Security** — Activity Log visible
- **Support** — Support Tickets and Tutorials visible

## Conclusion

The **Sales** role is designed for travel agents and sales representatives. It provides:
- **Full access** to all travel service booking modules (tickets, hotels, visas, umrah)
- **Client management** (create and manage customer records)
- **Reporting** across all service categories
- **No financial access** — cannot view accounts, debtors, creditors, expenses, or salaries
- **No administrative access** — cannot manage users, HR, or branch settings
