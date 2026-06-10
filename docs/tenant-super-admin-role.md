# Tenant Super Admin Role — Complete Documentation

## Overview

The **Tenant Super Admin** is a cross-branch administrative role operating at the tenant (agency) level — above individual branch admins but below the platform super admin. This role provides unified visibility and control across all branches within a single tenant while managing tenant-level configuration, subscriptions, users, and add-ons.

---

## Position in the RBAC Hierarchy

```
Platform Super Admin  ───  All tenants, global platform config
       │
Tenant Super Admin   ───  One tenant, ALL its branches
       │
Branch Admin         ───  One branch only (operational)
Branch Finance
Branch Sales
Branch Umrah Staff
Branch Staff
```

---

## What Problems It Solves

| Problem | Solution |
|---------|----------|
| Branch admins can only see their own branch data | Tenant Super Admin sees **all branches** in one place |
| No centralized user management across branches | Create/manage users for **any branch** in the tenant |
| Hard to compare branch performance | Cross-branch reports with branch comparison tables |
| Branch capacity limits are frustrating | Request **branch add-ons**, **user add-ons**, **communication add-ons** on demand |
| No tenant-level branding control | Set company logo, favicon, tax ID, registration number globally |
| Subscription/billing is opaque | View current plan, payment history, invoices, add-on costs |
| Templates can't be standardized | Create/manage **notification, email, and print templates** for all branches |
| WhatsApp needs central configuration | Configure provider, auto-notifications, rate limits tenant-wide |
| Reports are branch-scoped only | Generate **multi-branch profit/revenue reports** with PDF/Excel export & email delivery |

---

## Complete Feature Set

### 1. Cross-Branch Dashboard

**File:** `tenant_super_admin/dashboard.php`

| Widget | Detail |
|--------|--------|
| **KPI Cards** | Total branches, active users, ticket bookings, reservations, hotel bookings, visa applications, total revenue |
| **Revenue Trend** | Line chart over time |
| **Branch Comparison** | Bar chart comparing branch performance |
| **Monthly Booking Trends** | Booking volume over time |
| **Branch Filter** | Scope data to a single branch or all branches |
| **Activity Feed** | Recent actions from `activity_log` |
| **Service Breakdown** | Profit by service type (tickets, hotels, visa, umrah, etc.) |

---

### 2. Branch Management

**File:** `tenant_super_admin/branches.php`

| Feature | Detail |
|---------|--------|
| **Create Branch** | Name, code (auto/manual), address, phone, email, manager |
| **Edit Branch** | Update any branch details |
| **Toggle Status** | Activate/deactivate branches |
| **Limit Enforcement** | `BranchAddonManager` checks plan max + active add-ons before allowing creation |

---

### 3. User Management

**File:** `tenant_super_admin/users.php`

| Feature | Detail |
|---------|--------|
| **Create User** | Name, email, password (min 12 chars), role assignment, branch assignment |
| **Assignable Roles** | admin, finance, sales, umrah, staff, tenant_super_admin |
| **Limit Enforcement** | `UserAddonManager` checks plan max + active add-ons before allowing creation |
| **Usage Stats** | Shows current/max/available users + percentage used |
| **Activity Logging** | All user mutations logged to `activity_log` |

---

### 4. Subscription & Payments

**File:** `tenant_super_admin/subscription_payments.php`

| Feature | Detail |
|---------|--------|
| **Current Plan** | Name, price, billing cycle display |
| **Payment History** | All past payments with status, dates, amounts |
| **Invoice Management** | View/download invoices |
| **Add-on Costs** | See branch add-on, user add-on, communication add-on charges |
| **Currency** | USD and AFS support |

---

### 5. Tenant Settings

**File:** `tenant_super_admin/tenant_settings.php`

| Category | Settings |
|----------|----------|
| **Company Info** | Agency name, email, phone, address, logo, favicon, tax ID, registration number |
| **Notifications** | Email notification toggles |
| **SMTP** | SMTP server configuration (gated by Communication Add-on) |
| **Localization** | Language, timezone, date format, currency |

---

### 6. Profile & Security Settings

**File:** `tenant_super_admin/settings.php`

| Feature | Detail |
|---------|--------|
| **Profile** | Name, email, phone, address |
| **Password** | Change with current password verification |
| **2FA/TOTP** | Enable/disable two-factor authentication |
| **Session Timeout** | Configurable inactivity timeout |
| **Preferences** | Language, timezone, date format, currency, fiscal year start, auto backup toggle |

---

### 7. Cross-Branch Operational Views (Read-Only Oversight)

The tenant super admin can view **all records across all branches** for every operational module:

| Module | File | Branch Filter? |
|--------|------|----------------|
| Ticket Bookings | `ticket_bookings.php` | Yes |
| Ticket Reservations | `ticket_reservations.php` | Yes |
| Refunded Tickets | `refunded_tickets.php` | Yes |
| Date Change Tickets | `date_change_tickets.php` | Yes |
| Ticket Weights | `ticket_weights.php` | Yes |
| Hotel Bookings | `hotel_bookings.php` | Yes |
| Visa Applications | `visa_applications.php` | Yes |
| Umrah Bookings | `umrah_bookings.php` | Yes |
| Main Accounts | `main_accounts.php` | Yes |
| Suppliers | `suppliers.php` | Yes |
| Clients | `clients.php` | Yes |
| Debtors | `debtors.php` | Yes |
| Creditors | `creditors.php` | Yes |
| Expenses | `expenses.php` | Yes |
| Additional Payments | `additional_payments.php` | Yes |
| Salary Management | `salary_management.php` | Yes |
| Sarafi | `sarafi.php` | Yes |
| Branch Attendance | `branch_attendance.php` | Yes |
| Activity Logs | `activity_logs.php` | Yes |
| Email Analytics | `email_analytics.php` | Yes |

Each page includes transaction detail modals accessible via AJAX endpoints (`get_client_transactions.php`, `get_supplier_transactions.php`, `get_creditor_transactions.php`, `get_debtor_transactions.php`, `get_account_transactions.php`, `get_employee_payments.php`).

---

### 8. Reports & Analytics

#### 8.1 Multi-Branch Reports

**File:** `tenant_super_admin/reports.php`

| Feature | Detail |
|---------|--------|
| **Date Range** | Start/end date with comparison period |
| **Branch Filter** | Single branch or all |
| **Service Breakdown** | Tickets, hotels, visas, umrah, additional payments, refunds, date changes |
| **Dual Currency** | USD and AFS profit columns |
| **Top Clients** | Revenue by client |
| **Top Suppliers** | Spend by supplier |
| **Branch Comparison** | Side-by-side branch performance table |
| **Export** | PDF and Excel formats with email delivery |

#### 8.2 Report Settings

**File:** `tenant_super_admin/report_settings.php`

| Setting | Options |
|---------|---------|
| Auto-generation | Enable/disable monthly reports |
| Schedule | Day of month (1-31), time of day |
| Top clients limit | 5-50 |
| Top suppliers limit | 5-50 |
| Branch comparison | Toggle on/off |
| PDF attachment | Toggle on/off |
| Recipients | Add/remove email recipients |
| History | Report generation log with status |

#### 8.3 Comprehensive Export

**File:** `tenant_super_admin/export_comprehensive_report.php`

Full Excel export via PhpSpreadsheet with multi-currency totals (USD, AFS, EUR, DARHAM, pure_afs, usd_to_afs) across all service types.

---

### 9. Template Management

**File:** `tenant_super_admin/manage_templates.php`

| Feature | Detail |
|---------|--------|
| **Create Template** | Notification, email, and print templates |
| **Edit Template** | Update existing templates |
| **Delete Template** | Remove templates |
| **Categories** | Organized by type (e.g., Umrah Tazmin) |
| **Preview** | View template before use |
| **Scope** | Tenant-wide — all branches use the same templates |

---

### 10. WhatsApp Configuration

#### 10.1 Settings

**File:** `tenant_super_admin/whatsapp_settings.php`

| Feature | Detail |
|---------|--------|
| **Provider** | Meta WhatsApp Business API configuration |
| **Auto-Notifications** | Enable/disable automated alerts |
| **Real-Time Toggles** | Per-notification-type control |
| **Rate Limits** | Configure messaging limits |
| **Retry Logic** | Failed message retry configuration |
| **Gating** | Requires active WhatsApp Communication Add-on |

#### 10.2 Analytics

**File:** `tenant_super_admin/whatsapp_analytics.php`

| Metric | Detail |
|--------|--------|
| **Messages Sent** | Total volume |
| **Delivery Rate** | Percentage delivered |
| **Opt-Ins** | Users who opted in |
| **Opt-Outs** | Users who opted out |
| **Charts** | Time-series messaging activity |
| **Gating** | Requires active WhatsApp add-on |

---

### 11. Add-on Requests

The tenant super admin can request additional capacity beyond the plan limits:

| Add-on | Manager Class | Pricing Source | Request Flow |
|--------|---------------|----------------|--------------|
| **Branch Add-on** | `BranchAddonManager` | `settings.branch_addon_monthly/quarterly/yearly_price` (default $50/mo) | Form → `branch_addon_requests` (pending) → Platform super admin approves |
| **User Add-on** | `UserAddonManager` | `settings.user_addon_monthly/quarterly/yearly_price` (default $25/mo) | Form → `user_addon_requests` (pending) → Platform super admin approves |
| **Communication Add-on** | `CommunicationAddonManager` | `settings.whatsapp_addon_monthly/quarterly/yearly_price` ($30/mo) and `smtp_addon_monthly/quarterly/yearly_price` ($20/mo) | Form → `communication_addon_requests` (pending) → Platform super admin approves |

Each request page shows:
- Current usage vs maximum allowed
- Pricing per billing cycle (monthly/quarterly/yearly)
- Pending request status
- Prevents duplicate pending requests

---

### 12. Branch Context Switching

**File:** `tenant_super_admin/switch_branch.php`

Allows the tenant super admin to temporarily switch their session context to a specific branch:
- Dropdown of active branches
- Sets `$_SESSION['current_branch_id']`
- Logs to `activity_log` with `action_type = 'switch_branch'`
- Redirects back to referring page

This enables the tenant super admin to use **branch-level admin pages** (`admin/ticket.php`, `admin/accounts.php`, etc.) in the context of a specific branch.

---

### 13. Activity Logs (Cross-Branch)

**File:** `tenant_super_admin/activity_logs.php`

| Feature | Detail |
|---------|--------|
| **Filters** | Search text, user, action, table |
| **Stats** | Total logs, active users, active days |
| **Pagination** | 25 records per page |
| **Modal** | Summary + Old Values + New Values + Technical tabs |
| **Branch** | Shows `branch_name` via JOIN |

---

## Role Comparison

| Capability | Platform Super Admin | Tenant Super Admin | Branch Admin |
|------------|:---:|:---:|:---:|
| **Cross-branch visibility** | All tenants | Own tenant only | ❌ |
| **Manage branches** | Yes (all tenants) | Yes (own tenant) | ❌ |
| **Manage users** | Global | Tenant-wide | Branch only |
| **Assign tenant_super_admin role** | Yes | ❌ | ❌ |
| **Configure company branding** | ❌ | Yes | ❌ |
| **Manage templates** | ❌ | Yes | ❌ |
| **View subscription/payments** | All tenants | Own tenant only | ❌ |
| **Request add-ons** | N/A | Yes | ❌ |
| **Cross-branch reports** | All tenants | Own tenant only | ❌ |
| **WhatsApp settings** | ❌ | Yes (with addon) | ❌ |
| **Branch context switching** | ❌ | Yes | ❌ |
| **Operational tasks** | ❌ | View all branches | Own branch |
| **Platform settings** | Yes | ❌ | ❌ |
| **Manage tenants** | Yes | ❌ | ❌ |
| **Manage plans/pricing** | Yes | ❌ | ❌ |
| **Approve addon requests** | Yes | ❌ | ❌ |

---

## Session & Security

| Mechanism | Detail |
|-----------|--------|
| **Session timeout** | 30 minutes inactivity |
| **IP/UA binding** | Prevents session hijacking |
| **Session regeneration** | Every 5 minutes |
| **CSRF tokens** | All POST forms via `CsrfProtection` class |
| **Password policy** | Minimum 12 characters |
| **Rate limiting** | 5 failed login attempts → 30-minute lockout |
| **2FA/TOTP** | Optional two-factor authentication |
| **Payment gating** | Suspended tenants redirected to payment page |
| **Trial enforcement** | Expired trials auto-suspend |
| **Role path authorization** | Cross-role URL access blocked |

---

## File Map (55 files)

```
tenant_super_admin/
├── Dashboard & Profile
│   ├── dashboard.php              # Cross-branch KPIs + charts
│   ├── profile.php                # User profile
│   ├── update_profile.php         # Profile update handler
│   ├── settings.php               # Security + preferences
│   ├── updateSettings.php         # Settings update handler
│   └── logout.php                 # Logout
│
├── Tenant Administration
│   ├── branches.php               # Branch CRUD with add-on limit enforcement
│   ├── users.php                  # User management with add-on limit enforcement
│   ├── tenant_settings.php        # Company branding, SMTP, notifications
│   ├── switch_branch.php          # Branch context switching
│   ├── manage_templates.php       # Notification/email/print templates
│   └── subscription_payments.php  # Plan info + payment history
│
├── Add-on Requests
│   ├── request_branch_addon.php
│   ├── request_user_addon.php
│   └── request_communication_addon.php
│
├── WhatsApp
│   ├── whatsapp_settings.php      # Provider + notification config
│   └── whatsapp_analytics.php     # Message analytics dashboard
│
├── Reports & Exports
│   ├── reports.php                # Multi-branch profit/revenue reporting
│   ├── report_settings.php        # Monthly report schedule
│   ├── generate_report.php        # On-demand report generator
│   └── export_comprehensive_report.php  # Full Excel export
│
├── Cross-Branch Operational Views
│   ├── ticket_bookings.php
│   ├── ticket_reservations.php
│   ├── refunded_tickets.php
│   ├── date_change_tickets.php
│   ├── ticket_weights.php
│   ├── hotel_bookings.php
│   ├── visa_applications.php
│   ├── umrah_bookings.php
│   ├── main_accounts.php
│   ├── suppliers.php
│   ├── clients.php
│   ├── debtors.php
│   ├── creditors.php
│   ├── expenses.php
│   ├── additional_payments.php
│   ├── salary_management.php
│   ├── sarafi.php
│   ├── branch_attendance.php
│   ├── activity_logs.php
│   └── email_analytics.php
│
├── AJAX Endpoints
│   ├── get_branch.php
│   ├── get_user.php
│   ├── get_csrf_token.php
│   ├── get_account_transactions.php
│   ├── get_client_transactions.php
│   ├── get_creditor_transactions.php
│   ├── get_debtor_transactions.php
│   ├── get_supplier_transactions.php
│   ├── get_employee_payments.php
│   ├── hesabpay_callback.php
│   └── process_subscription_payment.php
│
├── Shared
│   ├── header.php                 # Navigation + feature gating
│   ├── footer.php
│   └── db_security.php
│
└── Support Files
    └── get_creditor_transactions.php
```

---

## Conclusion

The **Tenant Super Admin** is the central command role for an entire travel agency tenant. It bridges the gap between platform super admins (who manage the infrastructure) and branch-level staff (who handle day-to-day operations). This role provides:

- **Unified oversight** — See every branch, every user, every transaction
- **Centralized control** — Manage branches, users, templates, branding, WhatsApp
- **Scalability** — Request add-ons as the business grows
- **Data-driven decisions** — Cross-branch reports with comparison analytics
- **Self-service** — Manage subscription, settings, and configuration without platform intervention
