# Expense Management Module — Complete Feature Documentation

## Overview

The Expense Management module handles business expense recording, categorization, budget allocation, receipt uploads, multi-currency support, reporting with charts, Excel export, PDF generation, and main account ledger integration. Multi-tenant and multi-branch.

---

## 1. Expense Categories

**Admin page:** `admin/expense_management.php`

| Feature | Details |
|---------|---------|
| **Create Category** | Modal form with name validation, AJAX save to `expense_categories` |
| **Edit Category** | Click edit icon on category card → pre-populated modal → AJAX update |
| **Delete Category** | Confirm dialog → AJAX delete with cascade + activity log |
| **Collapsible Cards** | Each category as a card with header (name, count, total, actions) and expandable expense list |
| **Per-Category PDF** | Print button generates mPDF PDF for current month's expenses (RTL support) |

---

## 2. Expense Recording

**Modal:** `modals/expense/expense_modal.php` | **API:** `api/expense/expense_actions.php`

**Add Expense — 4 sections:**

| Section | Fields |
|---------|--------|
| Basic Information | Category (dropdown), Date (defaults to today), Description |
| Financial Details | Amount, Main Account, Currency (USD/AFS/EUR/DARHAM) |
| Budget Allocation | Optional — select existing allocation; locks currency/category/account |
| Receipt Information | Receipt number + file upload (PDF/JPG/PNG, max 5MB) |

**Edit Expense:** Pre-populated modal with all fields, allocation data fetched via AJAX

**Delete Expense:** Confirm dialog → reverses allocation OR refunds main account + deletes receipt file

**View Expense Detail:** `admin/expense_detail.php?id=X`
- Hero header (category badge, date, amount)
- Two-column info grid (description, category, amount, receipt, date, account, budget allocation)
- Transaction timeline — visual timeline of all linked `main_account_transactions`
- Receipt modal — lightbox for viewing/downloading receipt
- Sticky action bar: back + print

**Notifications:** Auto-creates notification on add (`expense`) and delete (`expense_delete`)

---

## 3. Budget Allocation (Per-Category)

**Page:** `admin/budget_allocations.php` | **Rollover:** `admin/budget_rollover.php`

- Create/edit monthly budget allocations per expense category + main account
- Linked expenses: currency locked, category locked, account disabled
- Allocation `remaining_amount` decremented by expense amount; no separate `main_account_transactions`
- Edit: previous allocation credited back, new allocation debited
- Delete: allocation `remaining_amount` credited back
- Rollover remaining amounts from previous month into current period

---

## 4. Global Budget Allocation (Branch-Wide)

**Page:** `admin/global_budget_allocation.php` | **API:** `admin/global_allocation_actions.php`

A separate budget pool at the branch level, not tied to specific expense categories.

### Table: `global_budget_allocations`
Columns: `id, main_account_id, allocated_amount, remaining_amount, currency, allocation_date, description, tenant_id, branch_id, created_at, updated_at`

### Monthly Filter
- Month/year selectors at top; shows allocations for the selected month
- Period banner displays the selected month/year

### Auto Rollover Logic
- When viewing a month >= current month, checks if rollover was done for that month
- If not done: fetches remaining amounts > 0 from previous month per `main_account_id` + `currency`
- Creates new allocation rows with description "Rollover from [Previous Month]" and deducted balance
- Marks old allocations with "(Rolled over)" appended to description and `remaining_amount` set to 0

### Summary Cards (per currency)
- **Total Allocated** — sum of all allocation amounts
- **Available Funds** — total remaining with percentage
- **Used Funds** — total used with usage rate percentage

### Allocation Cards
Each card shows:
- Allocation date, account name (Global budget)
- Allocated amount + currency
- Description (rollover entries marked with `↩` prefix and blue text)
- Progress bar: green (<50% used), amber (50-75%), red (>75%)
- Used amount vs available amount labels

### Card Actions
| Button | Function |
|--------|----------|
| **Fund** | Add more money to an allocation — deducts from main account, increases both allocated and remaining amounts |
| **Category Usage** | Shows modal with per-category spending breakdown (category pill, total used, currency, percentage bar) |
| **Expenses** | Shows modal with all expenses linked to this allocation — filterable by category, inline delete |
| **Transactions** | Shows modal with all fund transactions — debit/credit indicators, account info, allocated vs remaining display, inline delete |
| **Delete** | Deletes allocation only if no funds used (remaining == allocated); returns money to main account, adjusts subsequent transaction balances |

### Add Expense from Global Allocation
- Modal with category, date, description, amount, currency fields
- Currency limited to currencies that have allocations for the month
- Automatically links to the first available global allocation matching the currency
- Deducts from allocation `remaining_amount`, inserts expense row with `global_allocation_id`
- Creates notification

### Create New Global Allocation
- Modal with: main account, total amount, currency, date, description
- Validates main account exists and has sufficient balance
- Deducts from main account balance (by currency column)
- Inserts allocation row; creates `main_account_transactions` with type `debit`, `transaction_of = 'global_budget_allocation'`
- Activity log recorded

### Delete Fund Transaction
- Reverses the transaction: credits/debits main account, adjusts allocation amounts, updates subsequent transaction balances

### Delete Global Expense
- Returns amount to allocation `remaining_amount`, deletes expense row from `expenses` table

---

## 4. Main Account / Ledger Integration

**Non-Allocation Expenses:**
- Main account balance decremented (by currency column)
- `main_account_transactions` created with type `debit`, `transaction_of = 'expense'`
- Edit: previous account refunded, new account debited with new transaction
- Delete: account credited back, subsequent balances adjusted, transaction removed

---

## 6. Date Filtering

| Feature | Details |
|---------|---------|
| **Collapsible Panel** | Toggle visibility with slide animation |
| **Custom Range** | From/To date pickers |
| **Quick Ranges** | Today, Yesterday, This Week, This Month, Last Month, This Year |
| **Filter Badge** | Visual indicator when filter active |
| **URL-based State** | Parameters in URL for bookmarkable/shareable views |
| **Reset** | Clears params, returns to current-month default |

---

## 7. Reporting & Visualization

**API:** `api/expense/get_financial_data.php`

### Charts (Chart.js)
| Chart | Description |
|-------|-------------|
| **Income by Source** | Bar chart — tickets, reservations, refunds, date changes, visa, umrah, hotel, additional payments per currency |
| **Expense by Category** | Bar chart — expense categories per currency |
| **Profit/Loss** | Bar chart comparing profit vs loss per currency |
| **Export as PNG** | Download any chart as image |

### Excel Export
| File | Content |
|------|---------|
| `api/expense/export_expenses.php` | Date, Category, Description, Amount, Currency, Main Account, Created At + USD/AFS totals |
| `api/expense/export_comprehensive_report.php` | Multi-sheet P&L — income sources, expenses by category, currency conversion, profit/loss summary |

### Category PDF
- Monthly expense report per category via mPDF with RTL/Arabic support

---

## 8. Printing

**API:** `api/expense/print_expense.php?id=X`

- Agency logo, name, branch info
- Amount highlight strip
- Key-value detail grid (ID, category, date, account, receipt, allocation, description)
- Attached receipt image display
- Transaction history table
- Auto-print on load + Close/Print toolbar
- Print-optimized CSS

---

## 9. Super Admin (Tenant-Level)

**Page:** `tenant_super_admin/expenses.php`

- Summary stat cards: Total Expenses, Total USD, Total AFS, Average Amount
- Branch filter dropdown
- Search by description or category name
- Paginated table (25 per page) with branch pill, category badge, amount per currency
- Detail modal with Summary tab (description, category, branch, financial details) + Additional Info tab (ID, timestamps, receipt)

---

## 10. System-Level Expenses (Super Admin)

| Page | Purpose |
|------|---------|
| System-wide expense tracking separate from branch-level |
| Tables: `system_expense_categories`, `system_expenses` |

---

## 11. Database Tables

| Table | Purpose |
|-------|---------|
| `expense_categories` | Tenant/branch-level expense categories |
| `expenses` | Core expense records with allocation, global allocation, and receipt support |
| `global_budget_allocations` | Branch-wide budget pool per main account + currency, with rollover |
| `system_expense_categories` | System-wide categories (super admin) |
| `system_expenses` | System-wide expense records (super admin) |

---

## 12. Technical Architecture

### Admin Pages: 6 files
`admin/expense_management.php`, `admin/expense_detail.php`, `admin/budget_allocations.php`, `admin/budget_rollover.php`, `admin/global_budget_allocation.php`, `admin/global_allocation_actions.php`

### Modal Files: 3 files
`modals/expense/expense_modal.php`, `modals/expense/category_modal.php`, `modals/allocation/expense_modal.php`

### JavaScript: 9 files
`js/expense/` — expense_actions.js, expense_filters.js, expense_management.js, event_handlers.js, chart.js, report_filter.js, file_input_handler.js, index.js, README.md

### API Endpoints: 9 files
| File | Purpose |
|------|---------|
| `api/expense/expense_actions.php` | Main CRUD handler — save/delete category, save/delete expense, get expense |
| `api/expense/print_expense.php` | Print-formatted receipt page |
| `api/expense/export_expenses.php` | Excel export — Date/Category/Description/Amount/Currency/Account |
| `api/expense/export_comprehensive_report.php` | Multi-sheet P&L Excel report |
| `api/expense/export_financial_data.php` | Generic financial data export |
| `api/expense/get_financial_data.php` | JSON for Chart.js charts |
| `admin/expense_category_report.php` | Printable HTML monthly category report (parent includes sub-categories) |
| `api/report/load_expense_categories.php` | Categories JSON for report filters |

### Global Allocation Handler
| File | Purpose |
|------|---------|
| `admin/global_allocation_actions.php` | 8 AJAX actions: create_global_allocation, add_funds_global, delete_global_allocation, add_expense_global, get_global_expenses, delete_global_expense, get_fund_transactions, delete_fund_transaction |

### CSS: 1 file
`css/expenses/style.css`

### Super Admin: 1 file
`tenant_super_admin/expenses.php`

### Access Roles
`admin`, `finance` — full CRUD

### Security
- CSRF validation on all POST actions
- `DbSecurity::validateInput()` for all inputs
- `SecureFileUpload` for receipt files (max 5MB)
- Activity logging on all CRUD operations

### Multilingual
English, Dari (fa), Pashto (ps) with full translation sets
