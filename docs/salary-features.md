# Salary & Payroll Module — Complete Feature Documentation

## Overview

The Salary & Payroll module manages employee compensation: salary records, payment processing, advances, bonuses, deductions, salary adjustments, payroll reports, and printable receipts. Multi-tenant, multi-branch, dual-currency (USD/AFS).

---

## 1. Salary Management (Employee Records)

**Page:** `admin/salary_management.php`

| Feature | Details |
|---------|---------|
| **Add Salary Record** | Assign base salary, currency (USD/AFS), joining date, payment day to any employee without an existing record |
| **Edit Record** | Modify base salary, currency, payment day, status (active/inactive) |
| **Fire Employee** | One-click modal — sets `fired=1` in users table, salary status → inactive |
| **Status Badges** | Active (green), Inactive (orange), Fired (red gradient) |
| **Statistics Cards** | Total records, Active count, Fired/inactive count, Monthly payroll total |
| **Live Search & Filter** | By employee name; filter by All / Active / Inactive / Fired |
| **Links** | Quick access to bonuses, deductions, and print payroll |

### Salary Adjustment
**Page:** `admin/salary_adjustment.php`

| Feature | Details |
|---------|---------|
| **Increment/Decrement** | By fixed amount or percentage |
| **Validation** | Prevents negative salaries |
| **Audit Trail** | Records previous_salary, new_salary, approved_by, reason, effective_date |
| **Live Preview** | Shows new salary before submitting |

---

## 2. Payment Processing

**Page:** `admin/salary_payment.php`

### New Payment Form
| Field | Details |
|-------|---------|
| Employee | Only active employees with salary records |
| Main Account | Shows USD/AFS balance |
| Payment Month | YYYY-MM format |
| Number of Months | Supports batch multi-month payment |
| Amount | Auto-filled net salary from live AJAX calculation |
| Currency | Auto-detected from employee salary record |
| Payment Type | Regular Salary, Bonus, Advance, Other |
| Description | Auto-populated based on type |

### Live Salary Breakdown Panel
AJAX fetches when employee + month selected:
- Total advances for the month
- Total deductions (including absence)
- Total bonuses
- Base salary + bonuses - deductions - advances = net payable
- Already-paid warning if regular salary exists for this month
- Absence warning with one-click "Apply Deduction" button

### Key Behaviors
| Feature | Details |
|---------|---------|
| **Multi-Month** | Pay multiple months in one transaction; separate receipt codes per month |
| **Auto Advance Repayment** | Regular payments automatically deduct outstanding advances |
| **Account Balance** | Validates sufficient funds; decrements account on payment |
| **Transaction Logging** | Creates `main_account_transactions` with type `salary_payment` |
| **Email Notification** | Sends styled HTML via `sendSalaryPaymentNotification()` |
| **Receipt Numbering** | `SP` prefix + `YmdHis`; multi-month gets `-N` suffix |

### Edit / Delete Payment
- **Edit** — Inline modal: change amount, date, description; recalculates subsequent account balances
- **Delete** — Reverses account deduction, deletes transaction, reverts advance repayment statuses, logs activity

### My Payments (Employee Self-Service)
**Page:** `admin/salary_payments.php`
- Full payment history with search by receipt/description
- Month, type, currency filters
- Total USD and total AFS received display
- "Print My Payroll" button
- Live filter chips

---

## 3. Salary Advances

**Page:** `admin/salary_advances.php` | **Receipt:** `admin/print_salary_advance_receipt.php`

| Feature | Details |
|---------|---------|
| **Process Advance** | Select account, enter amount, currency, description |
| **Validation** | Amount must be positive, cannot exceed 3x monthly salary |
| **Account Balance** | Validates sufficient funds |
| **Dual-Table Insert** | Recorded in both `salary_advances` AND `salary_payments` (type `advance`) with same receipt code |
| **Repayment Tracking** | `repayment_status`: pending → partially_paid → paid; auto-updated when regular salary processed |
| **Receipt Printing** | Professional layout with logo, employee info, amount, signature fields, payment stamp |
| **Email Notification** | Sent via `sendSalaryAdvanceNotification()` |
| **History Table** | All advances for selected employee with print button |

---

## 4. Bonuses Management

**Pages:** `admin/manage_bonuses.php`, `admin/edit_bonus.php`, `admin/delete_bonus.php`

| Feature | Details |
|---------|---------|
| **Add Bonus** | Employee, amount, description, date, type (Performance / Holiday / Other) |
| **Records Table** | Employee, amount, type badge, date, description, created by, edit/delete |
| **Statistics** | Total count, total amount, active employees count |
| **Integration** | Bonuses factored into salary breakdown during payment processing |

---

## 5. Deductions Management

**Pages:** `admin/manage_deductions.php`, `admin/edit_deduction.php`, `admin/delete_deduction.php`

| Feature | Details |
|---------|---------|
| **Add Deduction** | Employee, amount, description, date, type (Absence / Penalty / Tax / Other) |
| **Records Table** | Full CRUD with type badges |
| **Auto Absence Deduction** | `admin/deduct_absence.php` calculates `(base_salary / 30) * absent_days`; prevents duplicates; requires `attendance` feature |
| **Integration** | Deductions factored into salary breakdown during payment processing |

---

## 6. Payroll Reports & Printing

**Page:** `admin/print_payroll.php`

| Feature | Details |
|---------|---------|
| **Individual Report** | `?user_id=X&month=M&year=Y` |
| **Group Report** | All active employees (no user_id) |
| **Report Content** | Employee info, payment summary, bonuses list, deductions list, advances list, net salary calculation, attendance summary, payment details |
| **Print Layout** | 1200+ lines CSS — branded header, monospaced financial data, color-coded badges |
| **Attendance Summary** | Total days, present/absent/late/half-day counts, total working minutes (if attendance enabled) |

---

## 7. Database Tables

| Table | Purpose |
|-------|---------|
| `salary_management` | Core employee salary record (base salary, currency, joining date, payment day, status) |
| `salary_payments` | Payment transactions (amount, date, month, type, receipt code) |
| `salary_advances` | Advance tracking with repayment status |
| `salary_deductions` | Deductions by type (absence, penalty, tax, other) |
| `salary_bonuses` | Bonuses by type (performance, holiday, other) |
| `salary_adjustments` | Increment/decrement audit trail (previous/new salary, approved by) |
| `payroll_records` | Legacy batch payroll bundles |
| `payroll_details` | Individual entries within batch payroll |

---

## 8. Technical Architecture

### Admin Pages: 14 files
| Page | Purpose |
|------|---------|
| `admin/salary_management.php` | Main salary dashboard |
| `admin/salary_payment.php` | Process payments |
| `admin/salary_payments.php` | Employee self-service payment history |
| `admin/salary_advances.php` | Process advances |
| `admin/salary_adjustment.php` | Increment/decrement salary |
| `admin/manage_bonuses.php` | Bonus CRUD |
| `admin/manage_deductions.php` | Deduction CRUD |
| `admin/edit_bonus.php` | Edit bonus |
| `admin/delete_bonus.php` | Delete bonus |
| `admin/edit_deduction.php` | Edit deduction |
| `admin/delete_deduction.php` | Delete deduction |
| `admin/print_payroll.php` | Payroll report print |
| `admin/print_salary_advance_receipt.php` | Advance receipt print |
| `admin/deduct_absence.php` | Auto absence deduction AJAX |

### AJAX Endpoints: 3 files
| File | Purpose |
|------|---------|
| `admin/get_salary_details.php` | Returns advances, deductions, bonuses, existing payments, absent days for employee+month |
| `admin/update_salary_payment.php` | Updates payment amount/date/description; recalculates balances |
| `admin/delete_salary_payment.php` | Deletes payment; reverses account; reverts advance statuses |

### Notifications
- `sendSalaryPaymentNotification()` — styled HTML email on payment
- `sendSalaryAdvanceNotification()` — styled HTML email on advance
- Both defined in `includes/functions.php`

### Access Roles
| Role | Access |
|------|--------|
| admin, finance | Full access — all pages |
| sales, umrah, staff | My Payments only (self-service) |

### Feature Gating
- `salary` feature flag controls entire module visibility
- `attendance` feature flag controls absence deduction integration

### Security
- CSRF validation on all POST actions
- All queries filtered by `tenant_id` and `branch_id`
- Database transactions for atomic financial operations
- Activity logging on payment updates and deletions

### Multilingual
English, Dari (fa), Pashto (ps) via `__()` translation function
