# 💰 M.Travels Expense Management System — Features

Track every business expense, organize by category, link to budgets, upload receipts, and get clear financial reports with charts and exports. All in one place.

---

## 📂 Expense Categories

- **Create Categories** — Add custom expense categories (e.g., Office Rent, Utilities, Marketing, Travel)
- **Edit or Delete** — Update category names or remove them anytime
- **Collapsible Cards** — Each category shows as a card with total expenses and count; click to expand and see all expenses in that category
- **Category Reports** — Generate a PDF report for any category showing all expenses for the current month

---

## ✏️ Recording Expenses

- **Add Expenses** — A simple form with 4 sections:
  1. **Basic Info** — Select category, pick date (defaults to today), enter description
  2. **Financial Details** — Enter amount, choose main account, select currency (USD, AFS, EUR, DARHAM)
  3. **Budget Allocation** — Optionally link to a budget allocation (category/currency/account auto-lock)
  4. **Receipt** — Upload receipt file (PDF, JPG, PNG) and enter receipt number
- **Edit Expenses** — Change any field anytime, including the linked budget allocation
- **Delete Expenses** — Remove with confirmation; budget allocations are automatically credited back
- **Expense Detail View** — Click any expense to see a full page with amount highlight, info grid, transaction timeline, and receipt preview
- **Auto Notifications** — System alerts are created when expenses are added or deleted

---

## 📊 Budget Allocation (Per Category)

- **Monthly Budgets** — Set monthly budget limits per expense category and account
- **Track Remaining** — Each budget shows how much is left after expenses are recorded
- **Rollover** — Move unused budget amounts from one month to the next with one click
- **Smart Linking** — When an expense is linked to a budget allocation, the budget remaining is automatically reduced

---

## 🌍 Global Budget Allocation (Branch-Wide Pool)

A separate budget system that works across all expense categories — just set a total amount per currency and spend against it.

- **Create a Budget Pool** — Set aside a total amount from a main account in USD, AFS, EUR, or DARHAM. The money is deducted from the main account automatically
- **Auto Rollover** — When you open a new month, any unused funds from the previous month are automatically carried forward — no manual work needed
- **Summary Cards** — See at a glance: total allocated, available funds (with percentage), and used funds with usage rate
- **Allocation Cards** — Each pool is shown as a card with a colored progress bar (green = good, amber = caution, red = nearly exhausted)
- **Add More Funds** — Click "Fund" to add more money to any pool at any time
- **Add Expenses Directly** — Record expenses directly against the global pool; the system automatically finds the right allocation and deducts the amount
- **View Spending** — Click "Category Usage" to see which expense categories are spending from the pool, with percentages
- **View Expenses** — Click "Expenses" to see every expense tied to a pool, filterable by category
- **View Transactions** — Click "Transactions" to see the full fund transaction history (debits and credits)
- **Delete Cleanup** — Delete individual expenses, fund transactions, or entire pools (only if no money has been spent)

---

## 🔎 Date Filtering

- **Custom Date Range** — Filter expenses by any from/to date
- **Quick Buttons** — Today, Yesterday, This Week, This Month, Last Month, This Year
- **Shareable Links** — Filter state is stored in the URL so you can bookmark or share filtered views
- **Reset Anytime** — Clear all filters and go back to the current month view

---

## 📈 Reports & Visualization

### Interactive Charts
- **Income by Source** — Bar chart showing income from tickets, hotels, visas, umrah, and more
- **Expense by Category** — Bar chart breaking down expenses by category
- **Profit/Loss** — Comparison chart showing profit vs loss
- **Export as Image** — Download any chart as a PNG image

### Excel Reports
- **Expense Export** — Download expenses with Date, Category, Description, Amount, Currency, Main Account
- **Comprehensive P&L Report** — Full multi-sheet profit & loss report with income sources, expenses by category, currency conversion, and summary

### Category PDFs
- Generate a monthly PDF report for any expense category (supports Dari/Pashto RTL text)

---

## 🖨️ Printing

- **Print Receipts** — Print professional expense receipts with:
  - Agency logo, name, and branch info
  - Amount highlighted
  - Full expense details (ID, category, date, account, receipt, description)
  - Attached receipt image
  - Transaction history
  - Auto-print feature

---

## 👑 Super Admin View

- **Cross-Branch Overview** — See all expenses across all branches
- **Summary Stats** — Total expenses, total USD, total AFS, average amount
- **Branch Filter** — Focus on expenses from a specific branch
- **Search** — Find expenses by description or category
- **Detail Modal** — Click any expense to see full details in a tabbed popup

---

## 🛡️ Security & Access Control

- **User Roles** — Admin and Finance roles have full access
- **Receipt Upload** — Secure file upload with type and size validation (max 5MB)
- **Activity Log** — Every action is logged for audit purposes
- **Tenant & Branch Isolation** — Each branch sees only their own expenses and budgets

---

## 💻 What You Get

| Resource | Count |
|----------|-------|
| Admin Pages | 6 |
| Modal Screens | 3 |
| JavaScript Modules | 9 |
| API Endpoints | 9 |
| Global Allocation Actions | 8 (create, fund, delete allocation; add/delete expense; view expenses & transactions; delete fund transaction) |
| Database Tables | 5 |
| Supported Currencies | 4 (USD, AFS, EUR, DARHAM) |
| Export Formats | Excel (.xlsx), PDF, PNG (charts) |
| Receipt Formats | PDF, JPG, PNG |
| Budget Features | Monthly per-category allocation, rollover, global branch-wide pool |
| Languages | 3 (English, Dari, Pashto) |

---

*Ready to manage your business expenses? All features are available from the Expense Management dashboard.*
