# Debtors Module — Complete Feature Documentation

## Overview

Multi-tenant, branch-aware debtor management with full transaction lifecycle, multi-currency payments, main account integration, exchange rate conversion, printable statements/receipts/agreements, email notifications, dashboard widget, and cross-tenant oversight.

---

## 1. Debtor CRUD

**Page:** `admin/debtors.php` (1,317 lines) | **API:** `api/debtor/debtors_handler.php` (473 lines)

| Action | Detail |
|--------|--------|
| **Add** | Name, email, phone, address, currency (USD/AFS/EUR/DARHAM), initial balance, main account selector, skip-deduction toggle, agreement terms. Creates debtor + initial debit transaction + optional main account deduction. |
| **Edit** | Name, email, phone, address, agreement terms. Balance & currency are read-only after creation. Full audit log via `activity_log` (`table_name = 'debtors'`). |
| **Deactivate / Reactivate** | Deactivate requires zero balance. Sets `status` to `active`, `paid`, or `inactive`. |
| **Delete** | Admin-only. Requires zero transactions. Physically deletes debtor + cascading transactions. |

---

## 2. Payments (Credit Transactions)

**API:** `api/debtor/debtors_handler.php` — action: `pay`

| Feature | Detail |
|---------|--------|
| **Amount** | Payment amount in any currency |
| **Currency** | Can differ from debtor's currency (triggers exchange rate conversion) |
| **Exchange Rate** | 3 scenarios based on AFS vs non-AFS direction |
| **Description** | Free-text payment description |
| **Reference Number** | Auto-generated (`PAY-YYYYMMDDHHMMSS-{id}`) or custom |
| **Payment Date** | Date of payment |
| **Deposit To Account** | Main account selector (USD/AFS/EUR/DARHAM balances) |
| **Dual-Entry** | Decreases debtor balance + increases main account balance |
| **Notification** | Creates `notifications` record with `transaction_type = 'debtor'` |

---

## 3. Transaction Management

**API:** `api/debtor/update_debtor_transaction.php` (267 lines)

| Feature | Detail |
|---------|--------|
| **Edit Transaction** | Update amount, description, reference_number. Recalculates debtor balance, main account balance, and running balances of all subsequent transactions. Logged to `activity_log` (`table_name = 'debtor_transactions'`). |
| **Delete Transaction** | Admin-only via `api/debtor/delete_debtor_transaction.php` (206 lines). Reverses debtor balance + main account balance; recalculates subsequent `main_account_transactions.balance` values. |
| **Delete Debtor** | Admin-only via `api/debtor/delete_debtor.php` (84 lines). Requires zero transactions. |
| **Print Receipt** | `api/debtor/print_debtor_receipt.php` (411 lines) — HTML receipt for single payment from `main_account_transactions`. |
| **Print Statement** | `api/debtor/print_debtor_statement.php` (751 lines) — Full statement with agency branding, summary cards, transaction timeline, agreement terms. |
| **Print Agreement** | `api/debtor/print_agreement.php` (597 lines) — Formal debt agreement document with terms & signature lines. |

---

## 4. Debtor Detail Page

**Page:** `admin/debtors_detail.php` (443 lines)

- Displays full debtor info + full transaction history table
- Accessed via `?id=` parameter

---

## 5. Currency & Exchange Rate

**Supported:** USD ($), AFS (؋), EUR (€), DARHAM (د.إ)

| Scenario | Conversion |
|----------|------------|
| Debtor currency = AFS | `converted_amount = amount * exchange_rate` |
| Payment currency = AFS | `converted_amount = amount / exchange_rate` |
| Neither is AFS | `converted_amount = amount / exchange_rate` |

**Reference number formats:**
- Initial debt: `DEBT-YYYYMMDDHHMMSS-{id}`
- No-deduction debt: `DEBT-NODEDUCT-YYYYMMDDHHMMSS-{id}`
- Payment: `PAY-YYYYMMDDHHMMSS-{id}` (or custom)
- Agreement: `DEBT-{id}-YYYYMMDD`
- Statement: `ST-{hash}`

---

## 6. Dashboard Widget

**JS:** `js/dashboard/dashboard-debtors.js` (143 lines) | **Modal:** `modals/dashboard/debtor_modal.php` (31 lines)

| Feature | Detail |
|---------|--------|
| **Balance Display** | Rose-colored debtor balance total on dashboard |
| **Transaction Type Filter** | Load debtors grouped by type (ticket, visa, umrah, hotel, etc.) |
| **Modal Popup** | Click to open full debtor list with details |

---

## 7. Super Admin Cross-Tenant View

**Page:** `tenant_super_admin/debtors.php` (587 lines) | **AJAX:** `tenant_super_admin/get_debtor_transactions.php` (245 lines)

| Feature | Detail |
|---------|--------|
| **Branch Filter** | Filter debtors by branch |
| **Search** | Search across all debtors in tenant |
| **Stats Cards** | Total debtors, active debtors, USD outstanding, AFS outstanding, average debt |
| **Modal Details** | Click a debtor to load full info + transactions with running balance via AJAX |

---

## 8. Report & Export Integration

**API:** `api/report/export_report.php` — `case 'debtor'`

| Feature | Detail |
|---------|--------|
| **Date Range** | Filter by payment date range |
| **Formats** | PDF (Dompdf), Excel (PhpSpreadsheet), Word (PhpWord) |
| **Columns** | ID, Debtor Name, Phone, Email, Address, Balance, Currency, Status, Paid Amount, Received Amount |
| **Statement Export** | `api/report/export_statement.php` includes debtor transactions via `CASE WHEN mt.transaction_of = 'debtor'` |
| **Feature Gate** | `debtors` feature flag in `report.js` |

---

## 9. Database Tables

| Table | Purpose |
|-------|---------|
| `debtors` | Main debtor records (name, email, phone, address, balance, currency, status, main_account_id, agreement_terms, branch_id, tenant_id) |
| `debtor_transactions` | Transaction log (debtor_id, amount, currency, transaction_type (debit/credit), description, reference_number, payment_date, branch_id, tenant_id) |
| `main_account_transactions` | Dual-entry linkage via `transaction_of = 'debtor'`, `reference_id` |
| `main_account` | Currency-specific balance columns updated on each payment |
| `notifications` | `transaction_type = 'debtor'` on each payment |
| `activity_log` | `table_name = 'debtors'` or `'debtor_transactions'` for all edits |

---

## 10. JavaScript Files (4 files)

| File | Purpose |
|------|---------|
| `js/debtor/debtors-interactions.js` (267 lines) | Edit transaction handler, delete transaction/debtor with SweetAlert2 |
| `js/debtor/currency-check.js` (36 lines) | Show/hide exchange rate field, calculate conversion direction |
| `js/debtor/form-protection.js` (146 lines) | Double-click prevention + loading animation for all forms |
| `js/dashboard/dashboard-debtors.js` (143 lines) | Dashboard widget: load debtors by type, populate modal |

---

## 11. Role & Permission Model

| Role | Access |
|------|--------|
| `admin` | Full CRUD, including delete debtor and delete transaction |
| `finance` | Everything except delete buttons (admin-only) |
| `tenant_super_admin` | Cross-tenant view-only via separate page |
| Others | No access (redirect to login) |

**Feature Gate:** `hasFeature('debtors', $allowed_features)` in admin and super admin menus + report.js.

**Pricing group:** `financial_management` in `includes/pricing-helper.php`.

---

## 12. Languages

| Language | File |
|----------|------|
| English | `includes/languages/en/common.php` (~25 strings) |
| Dari/Farsi | `includes/languages/fa/common.php` (~25 strings) |
| Pashto | `includes/languages/ps/common.php` (~25 strings) |

---

## 13. File Map

```
admin/
  debtors.php                   # Main CRUD page (1,317 lines)
  debtors_detail.php            # Single debtor detail + transactions (443 lines)
api/
  debtor/
    debtors_handler.php         # All POST actions (473 lines)
    update_debtor_transaction.php    # AJAX edit transaction (267 lines)
    delete_debtor.php               # Admin-only delete debtor (84 lines)
    delete_debtor_transaction.php   # Admin-only delete transaction (206 lines)
    print_debtor_statement.php      # Printable statement (751 lines)
    print_debtor_receipt.php        # Printable receipt (411 lines)
    print_agreement.php             # Printable agreement (597 lines)
  dashboard/
    get_debtors.php             # Dashboard widget data (273 lines)
tenant_super_admin/
  debtors.php                   # Cross-tenant overview (587 lines)
  get_debtor_transactions.php   # AJAX transactions for modal (245 lines)
js/
  debtor/
    debtors-interactions.js     # SweetAlert2 confirmations (267 lines)
    currency-check.js           # Exchange rate field toggle (36 lines)
    form-protection.js          # Double-click prevention (146 lines)
  dashboard/
    dashboard-debtors.js        # Dashboard widget (143 lines)
css/
  debtors/
    styles.css                  # Debtor-specific styling (357 lines)
modals/dashboard/
  debtor_modal.php              # Dashboard popup modal (31 lines)
uploads/tutorials/debtor_tutorials/
  debtors.png
  debtors-payments.png
  debtors-buttons.png
  add_debtors.png
```
