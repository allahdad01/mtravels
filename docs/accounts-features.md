# Accounts Module — Complete Feature Documentation

## Overview

The Accounts module is a centralized financial hub managing three account types (Main/Internal, Supplier, Client) with multi-currency balance tracking, fund flows, balance transfers, transaction history, status management, and integration with every revenue/expense module in the system.

---

## 1. Account Types & Sections

**Page:** `admin/accounts.php` (1,215 lines)

### 1.1 Main Accounts (admin-only)

| Feature | Detail |
|---------|--------|
| **Types** | `internal` (cash/operational) or `bank` (with bank account numbers for USD & AFS) |
| **Balances** | 4 currency columns: `usd_balance`, `afs_balance`, `euro_balance`, `darham_balance` |
| **Health Gauge** | Visual progress bar based on USD balance vs 200,000 threshold |
| **Fund Inline** | Quick-fund UI per account card — currency selector + amount + fund button |
| **Last Updated** | Timestamp from `main_account.last_updated` |

### 1.2 Supplier Accounts

| Feature | Detail |
|---------|--------|
| **Data Source** | `suppliers` table (active only) |
| **Category Filters** | Ticket, Visa, Umrah, Hotel (pill filters) |
| **Currency Filters** | USD, AFS, positive/negative balance |
| **Stat Strip** | Total USD, total AFS, total USD due, total AFS due |
| **Actions** | Fund (credit card icon), Bonus (gift icon), Withdraw (arrow-down icon), View Transactions, Toggle Status (admin) |

### 1.3 Client Accounts

| Feature | Detail |
|---------|--------|
| **Data Source** | `clients` table (active, `usd_balance` & `afs_balance`) |
| **Pill Filters** | Positive, Negative, Zero balance + inline search |
| **Actions** | Make Payment (credit card icon), View Transactions, Toggle Status (admin) |

---

## 2. Quick-Stat Sticky Bar

Persistent bar at top with live totals:

| Stat | Condition |
|------|-----------|
| Main USD | Admin only — sum of all `usd_balance` |
| Main AFS | Admin only — sum of all `afs_balance` |
| Supplier USD / AFS | Sum of positive supplier balances |
| Client Due USD / AFS | Sum of negative client balances |
| Active Accounts | Total count across all 3 account types |

---

## 3. Low Balance Alerts

| Alert | Source | Threshold |
|-------|--------|-----------|
| **Warning (amber)** | `api/dashboard/supplier_notification.php` | USD < $500, AFS < 20,000 |
| **Danger (red)** | `api/dashboard/client_notification.php` | USD < -$1,000, AFS < -20,000 |

---

## 4. Fund Operations

### 4.1 Fund Main Account
**UI:** Inline per-card fund row | **API:** `api/accounts/fund_main_account.php`
- Select currency (USD/AFS/EUR/DARHAM) + enter amount
- Opens remarks modal (`modals/accounts/remarks_modal.php`) before final submission

### 4.2 Fund Supplier
**Modal:** `modals/accounts/fund_supplier_modal.php` (110 lines) | **API:** `api/accounts/fund_supplier.php`
- Select source main account (loaded dynamically)
- Payment currency toggle, exchange rate if currencies differ
- Receipt number + remarks

### 4.3 Fund Client (Payment)
**Modal:** `modals/accounts/client_payment_modal.php` (691 lines — 7-step wizard) | **API:** `api/accounts/fundClient.php`
- Step 1: Choose balance currency (USD/AFS)
- Step 2: Choose payment currency
- Step 3: Exchange rate (only when different)
- Step 4: Amount with live conversion preview
- Step 5: Select main account
- Step 6: Receipt number
- Step 7: Remarks

### 4.4 Supplier Bonus
**Modal:** `modals/accounts/bonus_supplier_modal.php` (77 lines) | **API:** `api/accounts/add_supplier_bonus.php`

---

## 5. Withdraw & Transfer

### 5.1 Withdraw from Supplier
**Modal:** `modals/accounts/withdraw_supplier_modal.php` (75 lines) | **API:** `api/accounts/withdraw_fund.php`
- Select main account to receive funds, payment currency, exchange rate, amount, remarks, receipt

### 5.2 Transfer Between Main Accounts
**Modal:** `modals/accounts/transfer_modal.php` (138 lines) | **API:** `api/accounts/transfer_balance.php`
- From-account + from-currency dropdowns
- To-account + to-currency dropdowns
- Amount, exchange rate, description
- Visual separator with arrow icon

---

## 6. Transaction History Modals (3)

| Modal | Source | Filters |
|-------|--------|---------|
| `modals/accounts/main_account_transaction_history_modal.php` (121 lines) | `api/accounts/get_main_account_transactions.php` | Currency, receipt, date range |
| `modals/accounts/supplier_transaction_history_modal.php` (101 lines) | `api/accounts/get_supplier_transactions_main.php` | Receipt, date range |
| `modals/accounts/client_transaction_history_modal.php` (118 lines) | `api/accounts/get_client_transactions.php` | Currency, receipt, date range |

Each modal: paginated table, print/export PDF button, action buttons (edit, edit receipt, print receipt, delete) gated by role.

---

## 7. Transaction CRUD APIs

| Action | API | Detail |
|--------|-----|--------|
| **Edit** | `api/accounts/update_transaction.php` | Date, amount, currency, type, receipt, description |
| **Edit Receipt** | `api/accounts/update_receipt.php` | Update receipt number only |
| **Delete (main account)** | `api/accounts/delete_main_account_transaction.php` | Admin-only |
| **Delete (supplier)** | `api/accounts/delete_supplier_transaction.php` | Admin-only |
| **Delete (client)** | `api/accounts/delete_client_transaction.php` | Admin-only |
| **Print Receipt** | `api/accounts/print_fund_receipt.php` | Printable fund receipt |

---

## 8. Main Account CRUD (admin-only)

| Action | API | Modal |
|--------|-----|-------|
| **Add** | `api/accounts/add_main_account.php` | `modals/accounts/add_main_account_modal.php` (93 lines) — name, type (internal/bank), bank numbers, bank name, status |
| **Edit** | `api/accounts/edit_main_account.php` | `modals/accounts/edit_main_account_modal.php` (97 lines) — name, type, bank fields, status |
| **Toggle Status** | `api/accounts/toggle_account_status.php` | Via `status-management.js` with confirmation |

---

## 9. Supplier & Client Status Toggle (admin-only)

| API | Source |
|-----|--------|
| `api/accounts/toggle_supplier_status.php` | `status-management.js` |
| `api/accounts/toggle_client_status.php` | `status-management.js` |

---

## 10. JavaScript Files (8 files, ~2,844 lines total)

| File | Lines | Purpose |
|------|-------|---------|
| `js/accounts/filters.js` | 327 | Global search, type/status filters, supplier/client pill filters, no-results messaging |
| `js/accounts/toast-notifications.js` | 271 | Toast framework (success/error/info/warning) with dynamic CSS injection |
| `js/accounts/printing.js` | 145 | Print/PDF for main/supplier/client transaction tables |
| `js/accounts/account-management.js` | 648 | Add/edit main account, modal setup, datepickers, filters |
| `js/accounts/account-funding.js` | 503 | Fund supplier, fund main account, transfer balance, client funding |
| `js/accounts/account-withdrawal.js` | 128 | Withdraw supplier, exchange rate toggle |
| `js/accounts/transaction-management.js` | 621 | Transaction table rendering, pagination, edit/delete flows |
| `js/accounts/status-management.js` | 201 | Status toggle for main accounts, suppliers, clients |

---

## 11. Database Tables

### `main_account`
| Column | Type | Detail |
|--------|------|--------|
| id | int(11) PK | Auto-increment |
| tenant_id | int(11) FK | CASCADE on delete |
| name | varchar(100) | Account name |
| account_type | enum('internal','bank') | Default 'internal' |
| usd_balance | decimal(15,3) | |
| afs_balance | decimal(15,3) | |
| euro_balance | decimal(10,3) | |
| darham_balance | decimal(10,3) | |
| bank_account_number | varchar(50) | USD account number |
| bank_account_afs_number | varchar(100) | AFS account number |
| bank_name | varchar(100) | |
| status | enum('active','inactive') | Default 'active' |
| branch_id | bigint(20) | |

### `main_account_transactions`
| Column | Type | Detail |
|--------|------|--------|
| id | int(11) PK | Auto-increment (current: ~1460) |
| main_account_id | int(11) FK | |
| type | enum('credit','debit') | |
| amount | decimal(15,3) | |
| balance | decimal(15,3) | Running balance after transaction |
| currency | enum('USD','AFS','EUR','DARHAM') | |
| transaction_of | enum(28 values) | All business module types |
| reference_id | int(11) | Links to source module transaction |
| exchange_rate | decimal(10,5) | Cross-currency rate |
| receipt | varchar(100) | Receipt number |
| branch_id | bigint(20) | |

### Related Tables (FK to `main_account`)
`budget_allocations`, `date_change_umrah`, `debtors`, `umrah_booking_members`, `umrah_transactions`, `salary_advances`, `salary_payments`, `suppliers`, `clients`

---

## 12. Role & Permission Model

| Role | Access |
|------|--------|
| `admin` | Full — view main accounts section, fund/withdraw/transfer, add/edit main accounts, toggle status, delete transactions |
| `finance` | View main accounts section, fund supplier/client, view transactions — **cannot** add/edit main accounts, toggle status, or delete |
| Others | No access (redirect to login) |

**Feature gate:** Not feature-gated (no `hasFeature()` check — available to all plans with admin/finance roles).

---

## 13. Integration Map

| Module | Integration |
|--------|-------------|
| **Ticket** | `transaction_of = 'ticket_sale'`, `'ticket_refund'`, `'ticket_reserve'`, `'date_change'`, `'weight'` |
| **Visa** | `transaction_of = 'visa_sale'`, `'visa_refund'` |
| **Umrah** | `transaction_of = 'umrah'`, `'umrah_refund'`; `umrah_booking_members.main_account_id` |
| **Hotel** | `transaction_of = 'hotel'`, `'hotel_refund'` |
| **Expense** | `transaction_of = 'expense'`, `'budget_allocation'` |
| **Salary** | `transaction_of = 'salary_payment'`; `salary_advances.main_account_id` |
| **Debtors** | `transaction_of = 'debtor'`; `debtors.main_account_id` |
| **Creditors** | `transaction_of = 'creditor'` |
| **Sarafi** | `transaction_of = 'deposit_sarafi'`, `'hawala_sarafi'`, `'withdrawal_sarafi'` |
| **Additional Payments** | `transaction_of = 'additional_payment'` |
| **Supplier** | Main account selection in supplier funding/withdrawal |
| **Client** | Main account selection in client payments; `clients.main_account_id` |
| **Dashboard** | `get_financial_data.php` — aggregates all balances and transaction flow |
| **Notifications** | `approve_notification.php` — updates receipt/description across 12+ transaction types |
| **Global Search** | `admin/search.php` — searches `main_account_transactions` |
| **Reports** | `export_report.php`, `export_statement.php`, `fetch_report_data.php` — joins main account data |

---

## 14. API File Map (23 files in `api/accounts/`)

```
Account CRUD:
  add_main_account.php
  edit_main_account.php
  get_main_account.php
  get_main_accounts.php
  fetch_main_accounts.php

Transaction History:
  get_main_account_transactions.php
  get_supplier_transactions_main.php
  get_client_transactions.php

Fund / Wallet:
  fund_main_account.php
  fund_supplier.php
  fundClient.php
  withdraw_fund.php
  transfer_balance.php
  add_supplier_bonus.php

Transaction Mutation:
  update_transaction.php
  update_receipt.php
  delete_main_account_transaction.php
  delete_supplier_transaction.php
  delete_client_transaction.php

Status Toggle:
  toggle_account_status.php
  toggle_supplier_status.php
  toggle_client_status.php

Print:
  print_fund_receipt.php
```

---

## 15. Language Support

| Language | File |
|----------|------|
| English | `includes/languages/en/common.php` (~30+ keys) |
| Dari/Farsi | `includes/languages/fa/common.php` (~30+ keys) |
| Pashto | `includes/languages/ps/common.php` (~30+ keys) |

---

## 16. File Map

```
admin/
  accounts.php                              # Main page (1,215 lines)
  dashboard.php                             # Main accounts wealth row (line 620)
  search.php                                # Searches main_account_transactions (line 215)
modals/accounts/ (13 files)
  fund_supplier_modal.php                   # Supplier funding (110 lines)
  withdraw_supplier_modal.php               # Supplier withdrawal (75 lines)
  bonus_supplier_modal.php                  # Supplier bonus (77 lines)
  client_payment_modal.php                  # 7-step client payment wizard (691 lines)
  client_transaction_history_modal.php      # Client transaction history (118 lines)
  supplier_transaction_history_modal.php    # Supplier transaction history (101 lines)
  transfer_modal.php                        # Main account transfer (138 lines)
  main_account_transaction_history_modal.php # Main account history (121 lines)
  remarks_modal.php                         # Generic remarks (44 lines)
  edit_transaction_modal.php                # Edit transaction (117 lines)
  edit_receipt_modal.php                    # Edit receipt number (34 lines)
  edit_main_account_modal.php               # Edit main account (97 lines)
  add_main_account_modal.php                # Add main account (93 lines)
js/accounts/ (8 files)
  filters.js                                # Search/filter system (327 lines)
  toast-notifications.js                    # Toast framework (271 lines)
  printing.js                               # Print/PDF export (145 lines)
  account-management.js                     # Main account CRUD (648 lines)
  account-funding.js                        # Fund operations (503 lines)
  account-withdrawal.js                     # Withdrawal operations (128 lines)
  transaction-management.js                 # Transaction display/CRUD (621 lines)
  status-management.js                      # Status toggle (201 lines)
api/accounts/ (23 files)
css/
  account/styles.css                        # Page design (1,286 lines)
assets/css/
  transaction-account.css                   # Transaction table (213 lines)
```
