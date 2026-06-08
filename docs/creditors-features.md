# Creditors Module — Complete Feature Documentation

## Overview

Multi-tenant, branch-aware creditor management with full transaction lifecycle, multi-currency payments, main account integration, exchange rate conversion, printable statements/receipts, email notifications, and cross-tenant oversight.

---

## 1. Creditor CRUD

**Page:** `admin/creditors.php` (1,112 lines) | **API:** `api/creditor/creditor_handler.php` (859 lines)

| Action | Detail |
|--------|--------|
| **Add** | Name, email, phone, address, initial balance, currency (USD/AFS/EUR/DARHAM), main account selector, skip-main-account toggle. Creates creditor + initial transaction + main account entry. |
| **Edit** | Name, email, phone, address. Balance & currency are read-only after creation. |
| **Deactivate / Activate** | Toggle `status` between `active`/`inactive`. Auto-set to `inactive` when balance reaches zero. |
| **Delete** | Admin-only button. Confirmation modal warns if balance is non-zero. Cascades to transactions + main account. |

---

## 2. Payments (Credit Transactions)

**API:** `api/creditor/creditor_handler.php` — action: `pay`

| Feature | Detail |
|---------|--------|
| **Amount** | Payment amount in any currency |
| **Currency** | Can differ from creditor's currency (triggers exchange rate conversion) |
| **Exchange Rate** | Calculated based on AFS vs non-AFS direction |
| **Description** | Free-text payment description |
| **Reference Number** | Optional receipt number |
| **Payment Date** | Date of payment (not today's date) |
| **Paid From Account** | Main account selector (USD/AFS/EUR/DARHAM balances) |
| **Dual-Entry** | Updates both creditor balance AND main account balance |
| **Notification** | Creates `notifications` record with `transaction_type = 'creditor'` |
| **Email Alert** | Sends `sendAccountNotification()` email to creditor if email exists |

---

## 3. Transaction Management

**API:** `api/creditor/update_creditor_transaction.php` (AJAX)

| Feature | Detail |
|---------|--------|
| **Edit Transaction** | Update amount, description, reference_number. Recalculates creditor balance, main account balance, and running balances of all subsequent transactions. |
| **Delete Transaction** | Admin-only. Reverses creditor balance + main account balance changes. Logged to `activity_log`. |
| **Print Receipt** | `api/creditor/print_creditor_receipt.php` — HTML receipt with agency logo, transaction details, signature lines. |
| **Print Statement** | `api/creditor/print_creditor_statement.php` — Full statement with agency branding, summary cards (initial balance, paid, remaining, status), transaction timeline, "PAID" watermark for zero balance. |

---

## 4. Creditor Detail Page

**Page:** `admin/creditors_detail.php`

- Displays full creditor info + full transaction history table
- Accessed via `?id=` parameter

---

## 5. Currency & Exchange Rate

**Supported:** USD ($), AFS (؋), EUR (€), DARHAM (د.إ)

| Scenario | Conversion |
|----------|------------|
| Creditor currency = AFS | `converted_amount = amount * exchange_rate` |
| Payment currency = AFS | `converted_amount = amount / exchange_rate` |
| Neither is AFS | `converted_amount = amount / exchange_rate` |

**Main account** maintains separate balance columns: `usd_balance`, `afs_balance`, `euro_balance`, `darham_balance`.

---

## 6. Super Admin Cross-Tenant View

**Page:** `tenant_super_admin/creditors.php` | **AJAX:** `tenant_super_admin/get_creditor_transactions.php`

| Feature | Detail |
|---------|--------|
| **Branch Filter** | Filter creditors by branch |
| **Search** | Search across all creditors in tenant |
| **Stats Cards** | Total creditors, active creditors, USD outstanding, AFS outstanding, average credit |
| **Modal Details** | Click a creditor to load full info + transactions via AJAX |

---

## 7. Report & Export Integration

**API:** `api/report/export_report.php` — `case 'creditor'`

| Feature | Detail |
|---------|--------|
| **Date Range** | Filter by payment date range |
| **Formats** | PDF (PhpWord/TCPDF), Excel (PhpSpreadsheet) |
| **Columns** | ID, Creditor Name, Phone, Email, Address, Balance, Currency, Status, Paid Amount, Received Amount |
| **Feature Gate** | `creditors` feature flag in `report.js` |

---

## 8. Database Tables

| Table | Purpose |
|-------|---------|
| `creditors` | Main creditor records (tenant_id, name, email, phone, address, balance, currency, status, branch_id) |
| `creditor_transactions` | Transaction log (creditor_id, tenant_id, amount, currency, transaction_type (debit/credit), description, payment_date, reference_number, branch_id) |
| `main_account_transactions` | Dual-entry linkage via `transaction_of = 'creditor'`, `reference_id` |
| `main_account` | Currency-specific balance columns updated on each payment |
| `notifications` | `transaction_type = 'creditor'` on each payment |
| `activity_log` | `table_name = 'creditor_transactions'` for all edits |

---

## 9. JavaScript Files (6 files)

| File | Purpose |
|------|---------|
| `js/creditor/datatables_init.js` | DataTables init for creditors & transaction tables |
| `js/creditor/currency_check.js` | Show/hide exchange rate field, calculate conversion direction |
| `js/creditor/transaction_update.js` | AJAX update + double-click prevention on submit buttons |
| `js/creditor/print_receipt.js` | Open receipt in new tab |
| `js/creditor/modal_init.js` | Modal button handler + z-index management |
| `js/creditor/toast.js` | Toast notification class |

---

## 10. Role & Permission Model

| Role | Access |
|------|--------|
| `admin` | Full CRUD, including delete creditor and delete transaction |
| `finance` | Everything except delete buttons (admin-only) |
| `tenant_super_admin` | Cross-tenant view-only via separate page |
| Others | No access (redirect to login) |

**Feature Gate:** `hasFeature('creditors', $allowed_features)` in both admin and super admin menus.

---

## 11. Languages

| Language | File |
|----------|------|
| English | `includes/languages/en/common.php` (~40+ strings) |
| Dari/Farsi | `includes/languages/fa/common.php` (~50+ strings) |
| Pashto | `includes/languages/ps/common.php` (~40+ strings) |

---

## 12. File Map

```
admin/
  creditors.php              # Main CRUD page (1,112 lines)
  creditors_detail.php       # Single creditor detail + transactions
api/
  creditor/
    creditor_handler.php     # All POST actions (859 lines)
    update_creditor_transaction.php  # AJAX edit transaction
    print_creditor_statement.php     # Printable statement (601 lines)
    print_creditor_receipt.php       # Printable receipt
tenant_super_admin/
  creditors.php              # Cross-tenant overview
  get_creditor_transactions.php      # AJAX transactions for modal
js/creditor/
  datatables_init.js
  currency_check.js
  transaction_update.js
  print_receipt.js
  modal_init.js
  toast.js
css/creditors/
  styles.css
uploads/tutorials/creditor_tutorials/
  creditors.png
  creditors-buttons.png
  add-creditor.png
  add-creditor-payments.png
```
