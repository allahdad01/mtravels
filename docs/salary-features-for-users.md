# 💵 M.Travels Salary & Payroll System — Features

Manage employee salaries, process payments, track advances, handle bonuses and deductions, and generate professional payroll reports — all in one place.

---

## 👤 Employee Salary Records

- **Add Salary** — Assign a base salary, currency (USD or AFS), joining date, and payment day to any employee
- **Edit or Deactivate** — Update salary details anytime or mark an employee as inactive
- **Fire Employee** — One-click to mark an employee as terminated; salary record is automatically deactivated
- **Status at a Glance** — Green = Active, Orange = Inactive, Red = Fired
- **Dashboard Stats** — See total records, active employees, fired/inactive count, and total monthly payroll
- **Quick Search** — Find employees by name; filter by Active, Inactive, or Fired

### Salary Adjustments

- **Increase or Decrease** — Adjust salary by a fixed amount or by percentage
- **Live Preview** — See the new salary before you submit
- **Full History** — Every adjustment is recorded: who approved it, the previous salary, the new salary, the reason, and the effective date

---

## 💳 Processing Payments

- **Process Monthly Salary** — Select employee, choose payment month, and the system calculates the net pay automatically
- **Live Breakdown Panel** — When you select an employee and month, the system shows:
  - Base salary
  - Total bonuses added
  - Total deductions subtracted
  - Outstanding advances
  - Net payable amount
  - Any already-paid warnings
- **Pay Multiple Months** — Process salary for several months in one transaction
- **Supported Payment Types** — Regular Salary, Bonus, Advance, Other
- **Automatic Advance Repayment** — When you pay a regular salary, outstanding advances are automatically deducted
- **Account Balance Check** — System verifies the account has enough funds before processing
- **Email Notification** — Employee receives a professional email with payment details
- **Edit or Delete Payments** — Fix mistakes; the system automatically adjusts account balances

### Employee Self-Service

- **My Payments** — Employees can view their own complete payment history
- **Search & Filter** — By receipt number, description, month, type, or currency
- **Total Received** — See total USD and total AFS received
- **Print My Payroll** — Generate a personal payroll report

---

## 💰 Salary Advances

- **Process an Advance** — Give an employee advance pay against future salary
- **Safe Limits** — Advance cannot exceed 3 times the monthly salary
- **Automatic Repayment** — When the next regular salary is processed, the advance is automatically deducted
- **Track Repayment** — Status shows: Pending → Partially Paid → Paid
- **Print Receipt** — Professional advance receipt with company logo, employee info, signature fields, and payment stamp
- **Email Notification** — Employee receives an advance confirmation email

---

## 🎁 Bonuses

- **Add Bonuses** — Performance, Holiday, or Other — with amount, description, and date
- **Full Records** — See all bonuses with employee name, amount, type badge, and who added it
- **Automatic Integration** — Bonuses are automatically included in the salary breakdown when processing payments
- **Edit or Delete** — Manage bonus records anytime

---

## ⚠️ Deductions

- **Add Deductions** — Absence, Penalty, Tax, or Other — with amount, description, and date
- **Auto Absence Deduction** — If attendance tracking is enabled, the system can automatically calculate absence deductions: `(base salary / 30) × absent days`
- **Full Records** — See all deductions with type badges
- **Automatic Integration** — Deductions are automatically subtracted in the salary breakdown
- **Edit or Delete** — Manage deduction records anytime

---

## 🖨️ Payroll Reports & Printing

- **Individual Payroll Report** — Print a detailed report for one employee showing: personal info, base salary, bonuses, deductions, advances, net pay, attendance summary, and payment history
- **Group Payroll Report** — Print for all active employees at once
- **Professional Layout** — Branded header, clear financial data, color-coded status badges, print-friendly design
- **Advance Receipts** — Printable receipts with signature fields and payment stamp

---

## 🔔 Notifications

- **Payment Confirmation** — Employees receive an email when salary is processed
- **Advance Confirmation** — Employees receive an email when an advance is given
- Both emails are professional HTML with your agency branding

---

## 🛡️ Security & Access Control

- **Role-Based Access** — Admin and Finance have full access; other roles can only view their own payments
- **Tenant & Branch Isolation** — Each branch sees only their own employees and salaries
- **Transaction Safety** — All financial operations use database transactions to prevent data corruption
- **Audit Trail** — Salary adjustments, payment edits, and deletions are all logged
- **Dual-Currency** — USD and AFS (Afghani)

---

## 💻 What You Get

| Resource | Count |
|----------|-------|
| Admin Pages | 14 |
| AJAX Endpoints | 3 |
| Database Tables | 8 |
| Supported Currencies | 2 (USD, AFS) |
| Payment Types | 4 (Regular, Bonus, Advance, Other) |
| Deduction Types | 4 (Absence, Penalty, Tax, Other) |
| Bonus Types | 3 (Performance, Holiday, Other) |
| Adjustment Types | 2 (Increment, Decrement) |
| Notification Channels | Email |
| Languages | 3 (English, Dari, Pashto) |

---

*Ready to manage your payroll? All features are available from the Salary Management dashboard.*
