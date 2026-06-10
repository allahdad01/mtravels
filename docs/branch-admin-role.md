# Branch Admin Role — Complete Documentation

## Overview

The **Branch Admin** is the operational manager of a single branch. This role has full access to all modules within the branch — the only branch-level restriction being that data is scoped to `tenant_id` + `branch_id`. The Branch Admin cannot see other branches' data (unlike Tenant Super Admin).

## RBAC Position

```
Platform Super Admin
    └── Tenant Super Admin  (all branches)
            └── Branch Admin  (one branch, full access)
                    ├── Finance  (one branch)
                    ├── Sales  (one branch)
                    ├── Umrah  (one branch)
                    └── Staff  (one branch, minimal)
```

## Session & Authorization

| Mechanism | Detail |
|-----------|--------|
| `staffCanSeeMenu()` | Returns `true` — sees full navigation |
| `allowed_admin_roles` | `['admin', 'tenant_super_admin', 'finance', 'sales', 'umrah', 'staff']` — allowed in `admin/` |
| `$isAdmin` flag | `$_SESSION['role'] === 'admin'` — used in accounts, debtors, creditors for destructive actions |
| Dashboard staff redirect | NOT redirected (only `staff` role is) |

## Dashboard (`admin/dashboard.php`)

The Branch Admin sees the **full dashboard** including admin-only widgets that other roles cannot see:

| Widget | Visibility |
|--------|-----------|
| KPI Cards (total revenue, bookings, clients) | All roles |
| Today's Attendance & Recent Bookings | All roles |
| Monthly Booking Trend Chart | All roles |
| Supplier & Client Alerts | All roles |
| **Financial Wealth Distribution Chart** | **Admin only** |
| **Performance Overview / Sales Cards** | **Admin only** |
| **Top Performers Leaderboard** | **Admin only** (`$user['role']==='admin'`) |
| Top Performers + Client Debts | Admin + Finance |

## Feature Access

All features that the tenant's plan enables are fully accessible to the Branch Admin. The role does not impose additional feature restrictions beyond `$allowed_features` (plan-level gating).

### Travel & Services
| Module | Files | Access |
|--------|-------|--------|
| **Ticket Bookings** | `ticket.php`, `view_tickets.php`, `view_ticket_details.php` | Full CRUD |
| **Ticket Reservations** | `ticket_reserve.php`, `ticket_reservation_detail.php` | Full CRUD |
| **Refunded Tickets** | `refund_ticket.php` | Full CRUD |
| **Date Change Tickets** | `date_change.php` | Full CRUD |
| **Ticket Weights** | `ticket_weights.php` | Full CRUD |
| **Hotel Bookings** | `hotel.php`, `hotel_detail.php` | Full CRUD |
| **Hotel Refunds** | `hotel_refunds.php` | Full CRUD |
| **Umrah Bookings** | `umrah.php`, `umrah_detail.php`, `umrah_services.php` | Full CRUD |
| **Umrah Refunds** | `umrah_refunds.php` | Full CRUD |
| **Umrah Date Changes** | `umrah_date_changes.php` | Full CRUD |
| **Visa Applications** | `visa.php`, `visa_detail.php` | Full CRUD |
| **Visa Refunds** | `visa_refunds.php` | Full CRUD |

### Finance & Accounting
| Module | Files | Access Level |
|--------|-------|-------------|
| **Accounts** | `accounts.php` | **Full** (main accounts management, activate/deactivate suppliers, delete transactions) |
| **Debtors** | `debtors.php`, `debtors_detail.php` | **Full** (delete debtors, delete transactions) |
| **Creditors** | `creditors.php`, `creditors_detail.php` | **Full** (delete creditors, delete transactions) |
| **Sarafi** | `sarafi.php` | Full CRUD |
| **Expenses** | `expense_management.php`, `expense_detail.php` | Full CRUD |
| **Budget Allocation** | `budget_allocations.php` | Full CRUD |
| **Global Budget Allocation** | `global_budget_allocation.php`, `global_allocation_actions.php` | Full CRUD |
| **Additional Payments** | `additional_payments.php`, `additional_payments_detail.php` | Full CRUD |
| **JV Payments** | `jv_payments.php`, `process_client_supplier_jv.php` | Full CRUD |
| **Reports** | `report.php` | Full (all categories: ticket, hotel, visa, umrah) |
| **Quarterly Tax Report** | `quarterly_tax_report.php` | **Admin only** (`$user['role']==='admin'`) |

### People & HR
| Module | Files | Access Level |
|--------|-------|-------------|
| **HR Dashboard** | `hr.php` | Full |
| **Employee Management** | `employee_management.php`, `add_employee.php`, `edit_employee.php`, `employee_details.php` | Full |
| **Performance Reviews** | `employee_performance.php` | Full |
| **My Attendance** | `attendance.php` | Full |
| **Manage Attendance** | `manage_attendance.php` | Full |
| **Attendance Settings** | `attendance_settings.php` | Full |
| **Salary Management** | `salary_management.php`, `salary_payment.php`, `salary_payments.php` | Full |
| **HR Reports** | `hr_reports.php` | Full |

### Operations
| Module | Files | Access |
|--------|-------|--------|
| **Letters (Maktobs)** | `manage_maktobs.php` | Full CRUD |
| **Assets** | `assets.php` | Full CRUD |
| **Excel Import** | `excel_import.php`, `excel_import_handler.php` | Full |
| **Search** | `search.php` | Full |
| **Suppliers** | `supplier.php`, `supplier_detail.php` | Full CRUD |
| **Clients** | `client.php`, `client_detail.php`, `customers.php` | Full CRUD |

### Communication
| Module | Files | Access |
|--------|-------|--------|
| **Chat** | `chat.php` (in root) | Full (if feature enabled) |
| **Chat Settings** | `chat_settings.php` | Full |
| **Branch Peering** | `branch_peering.php` | Full |
| **Email Analytics** | `email_analytics.php` | Full (if SMTP add-on active) |

### Security & Support
| Module | Files | Access |
|--------|-------|--------|
| **2FA** | `totp_setup.php` | Full |
| **Activity Log** | `activity_log.php` | Full |
| **Support Tickets** | `support_tickets.php`, `support_ticket_create.php`, `support_ticket_detail.php` | Full |
| **Tutorials** | `tutorial.php`, `tutorial_manager.php` | Full |

### Admin-Only Actions (gated by `$isAdmin`)
| Page | Gated Action |
|------|-------------|
| `accounts.php` | View main account balances, manage internal/main accounts, activate/deactivate suppliers, delete supplier transactions |
| `debtors.php` | Delete debtors, delete debtor transactions |
| `creditors.php` | Delete creditors, delete creditor transactions |
| `dashboard.php` | Financial Wealth Distribution chart, Performance Overview cards, Top Performers |
| `report.php` | Quarterly Tax Report submenu item |
| `users.php` | User management (add/edit/delete branch users) |
| `hr.php` | Full HR operations (add/edit/delete employees) |

## Shared Directory

The Branch Admin operates entirely from the `admin/` directory (126 PHP files), shared with finance, sales, umrah, and staff roles. Authorization is enforced by:
1. `session_check.php` — allows `admin` role in the `admin/` directory
2. `staffCanSeeMenu()` — returns `true` for admin
3. `$isAdmin` flag — additional destructive/management gating

## File Map (Key Files Only)

```
admin/
├── dashboard.php              # Full admin dashboard with admin-only widgets
├── accounts.php               # Full accounts with main account management
├── debtors.php                # Full debtor management with delete
├── creditors.php              # Full creditor management with delete
├── users.php                  # Create/edit/delete branch users
├── ticket.php                 # Ticket booking
├── hotel.php                  # Hotel booking
├── umrah.php                  # Umrah booking
├── visa.php                   # Visa applications
├── expense_management.php     # Expense management
├── salary_management.php      # Salary management
├── report.php                 # Reports (all categories)
├── quarterly_tax_report.php   # Quarterly tax (admin-only)
├── hr.php                     # HR dashboard
├── employee_management.php    # Employee CRUD
├── supplier.php               # Supplier management
├── client.php                 # Client management
├── activity_log.php           # Activity log
├── support_tickets.php        # Support tickets
└── ... (126 total PHP files)
```

## Conclusion

The **Branch Admin** is the highest operational role within a single branch. It has unrestricted access to all modules, data, and management actions that the tenant's plan allows. The only limitations are:
- **Data scope** — restricted to the admin's assigned branch (`branch_id`)
- **Plan features** — restricted by the tenant's subscription (`$allowed_features`)
- Cannot manage other branches, tenant settings, subscriptions, or templates (those belong to Tenant Super Admin)
