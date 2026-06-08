# Additional Payments Module — Complete Feature Documentation

## Overview

Multi-tenant, branch-aware additional payments system for recording non-standard income with profit tracking, supplier/client balance linkage, multi-currency transaction management, and full audit trail. Integrates with main accounts, supplier accounts, client accounts, dashboard sales/profit calculations, expense reports, and global search.

---

## 1. Payment CRUD

**Page:** `admin/additional_payments.php` (975 lines)

| Action | API | Detail |
|--------|-----|--------|
| **Add** | `api/additional_payment/add_additional_payment.php` (237 lines) | type, description, base_amount, sold_amount, profit (= base - sold), currency (USD/AFS), main_account, optional supplier/client linkage. Creates payment record + adjusts supplier/client balances. Logs to `activity_log`. |
| **Edit** | `api/additional_payment/update_additional_payment_base.php` (701 lines) | Update type, description, amounts, currency, linked supplier/client. Re-syncs supplier/client balances on changes. |
| **Delete** | `api/additional_payment/delete_additional_payment.php` (299 lines) | Blocks if transactions exist. Reverses all supplier/client/main_account balance changes. Deletes all associated transactions. Logs to `activity_log`. |

---

## 2. Transaction Management

| Action | API | Detail |
|--------|-----|--------|
| **Add Transaction** | `api/additional_payment/add_additional_payment_transaction.php` (182 lines) | Credit transaction to main_account. Supports 4 currencies with default exchange rates (USD→AFS=70, USD→EUR=0.9, USD→DARHAM=3.61). Creates notification (`transaction_type = 'additional_payment'`). Logs to `activity_log`. |
| **Edit Transaction** | `api/additional_payment/update_additional_payment_transaction.php` (190 lines) | Update amount, description, receipt. Adjusts main_account balance by delta. Logs to `activity_log`. |
| **Delete Transaction** | `api/additional_payment/delete_additional_payment_transaction.php` (123 lines) | Reverses main_account balance change. Logs to `activity_log`. |
| **List Transactions** | `api/additional_payment/get_transactions.php` (36 lines) | Returns all `main_account_transactions` for a given payment_id. |
| **Print Receipt** | `api/additional_payment/print_additional_receipt.php` (397 lines) | Printable HTML receipt with branch settings. |

---

## 3. Payment Status Computation

| Status | Condition |
|--------|-----------|
| **Unpaid** | totalPaidInBase <= 0 |
| **Partial** | totalPaidInBase < sold_amount (within 0.01 tolerance) |
| **Paid** | totalPaidInBase == sold_amount (within 0.01 tolerance) |
| **Overpaid** | totalPaidInBase > sold_amount |

---

## 4. Supplier & Client Linkage

| Link | Effect |
|------|--------|
| `is_from_supplier` | Reduces supplier balance by `base_amount`. Creates `supplier_transactions` record (`type = 'debit'`). |
| `is_for_client` | Reduces client balance by `sold_amount`. Creates `client_transactions` record (`type = 'debit'`). |

**Detail page** (`admin/additional_payments_detail.php`, 819 lines): shows transactions from main_account, supplier, and client tables.

---

## 5. Client-Facing Pages

| Page | Purpose |
|------|---------|
| `client/additional_payments.php` (935 lines) | List page with hero banner, summary cards (total count, USD total, AFS total, grand total), currency filter. |
| `client/additional_payments_detail.php` (677 lines) | Detail view showing payment info + client transactions only. |

---

## 6. Super Admin Cross-Branch View

**Page:** `tenant_super_admin/additional_payments.php` (541 lines)

| Feature | Detail |
|---------|--------|
| **Stats Grid** | Total payments, USD total, AFS total, USD profit, AFS profit |
| **Filter** | By branch + search |
| **UI** | Plus Jakarta Sans font, gradient stat cards |

---

## 7. Dashboard Integration

| Integration | Location | Detail |
|-------------|----------|--------|
| **Due Card** | `admin/dashboard.php` line 773 | Shows USD + AFS dues with progress bar. Feature-gated. |
| **Dues Summary** | `api/dashboard/get_dues_summary.php` lines 506-551 | Calculates unpaid amounts with multi-currency conversion. |
| **Profit Sources** | `api/dashboard/get_profit_sources.php` line 53 | Included in daily/weekly/yearly profit. |
| **Filtered Sales** | `api/dashboard/get_filtered_sales.php` | Profit aggregated by day/month/year. |
| **Payment Details** | `api/dashboard/get_payment_details.php` | Payment info for dashboard widgets. |
| **Debtors** | `api/dashboard/get_debtors.php` | Included in debtor list for agency clients. |

---

## 8. Report & Export Integration

| Feature | File | Detail |
|---------|------|--------|
| **Report Data** | `api/report/fetch_report_data.php` lines 222, 743 | Fetches additional payment data. |
| **Report Export** | `api/report/export_report.php` line 445 | `case 'additional_payment'` — PDF/Excel/Word export. |
| **Statement Export** | `api/report/export_statement.php` lines 492-621 | Joins additional_payments to main_account_transactions. |
| **Expense Report** | `api/expense/export_comprehensive_report.php` | Comprehensive multi-source income report. |
| **Tenant Export** | `tenant_super_admin/export_comprehensive_report.php` | Multi-currency totals (USD, AFS, EUR, DARHAM, pure_afs, usd_to_afs). |
| **Feature Gate** | `js/report/report.js` line 87 | Report option gated by `additional_payments` feature. |

---

## 9. Global Search

**Page:** `admin/search.php` lines 129-137

- Searches by payment_type, description, amount
- Links to `additional_payments_detail.php`
- Icon: `fa-credit-card`

---

## 10. Accounts Integration

| Page | Detail |
|------|--------|
| `api/accounts/get_main_account_transactions.php` | Joins additional_payments for display with `transaction_of = 'additional_payment'` |
| `api/accounts/get_supplier_transactions_main.php` | Joins additional_payments for display |
| `api/accounts/get_client_transactions.php` | Joins additional_payments for display |

---

## 11. Notification Approval

**API:** `api/dashboard/approve_notification.php` lines 276-304

- Handles notification approval/update for additional_payment transactions
- Updates receipt/description on the `main_account_transaction`

---

## 12. Database Tables

| Table | Purpose |
|-------|---------|
| `additional_payments` | Main payment records (id, tenant_id, payment_type, description, base_amount, sold_amount, profit, currency [USD/AFS ENUM], main_account_id, created_by, receipt, supplier_id, is_from_supplier, client_id, is_for_client, branch_id) |
| `main_account_transactions` | Credit transactions with `transaction_of = 'additional_payment'` |
| `supplier_transactions` | Debit transactions when `is_from_supplier` |
| `client_transactions` | Debit transactions when `is_for_client` |
| `notifications` | `transaction_type = 'additional_payment'` |
| `activity_log` | `table_name = 'additional_payments'` or `'main_account_transactions'` |

---

## 13. Modals (4 files)

| Modal | File |
|-------|------|
| Add Payment | `modals/additional_payment/add_payment_modal.php` (100 lines) |
| Edit Payment | `modals/additional_payment/edit_payment_modal.php` (103 lines) |
| Manage Transactions | `modals/additional_payment/add_transaction_modal.php` (211 lines) — 4 currency sections (USD/AFS/EUR/AED) |
| Edit Transaction | `modals/additional_payment/edit_transaction_modal.php` (85 lines) |

---

## 14. JavaScript Files (2 files)

| File | Purpose |
|------|---------|
| `js/additional_payments/main.js` (787 lines) | Form submissions (add/edit/delete), supplier/client checkbox toggles, profit auto-calculation, edit-modal population |
| `js/additional_payments/transactions.js` (552 lines) | Transaction manager — load history, paid/remaining per currency, exchange rate conversions, edit/delete/print |

---

## 15. Role & Permission Model

| Role | Access |
|------|--------|
| `admin` | Full CRUD — add, edit, delete payments and transactions |
| `finance` | Same as admin (no additional restrictions found) |
| `tenant_super_admin` | Cross-branch view-only |
| `staff` | No access (not in `$allowed_roles`) |
| `client` | View own payments via client-facing pages |

**Feature Gate:** `hasFeature('additional_payments', $allowed_features)`

**Pricing group:** `financial_management` in `includes/pricing-helper.php`.

---

## 16. Languages

| Language | File |
|----------|------|
| English | `includes/languages/en/common.php` (~26 keys + 12 tutorial keys) |
| Dari/Farsi | `includes/languages/fa/common.php` (~25 keys + tutorial keys) |
| Pashto | `includes/languages/ps/common.php` (~25 keys + tutorial keys) |

---

## 17. Known Issues

| Issue | Detail |
|-------|--------|
| **Currency ENUM mismatch** | DB column is `ENUM('USD','AFS')` but UI allows EUR and DARHAM selection |
| **Missing activity log on edit** | `update_additional_payment_base.php` does not log payment-level edits to `activity_log` |
| **branch_id type mismatch** | `bigint(20)` in DB vs `int(11)` in related tables; no FK constraint |
| **Hardcoded exchange rates** | Default rates (USD→AFS=70, USD→EUR=0.9, USD→DARHAM=3.61) are hardcoded in JS/PHP |
| **No supplier_id FK** | Unlike `client_id`, `supplier_id` has no foreign key constraint |

---

## 18. File Map

```
admin/
  additional_payments.php                   # Main list page (975 lines)
  additional_payments_detail.php            # Detail view (819 lines)
  search.php                                # Global search integration
api/
  additional_payment/
    add_additional_payment.php              # Create payment (237 lines)
    add_additional_payment_transaction.php  # Add transaction (182 lines)
    delete_additional_payment.php           # Delete payment (299 lines)
    delete_additional_payment_transaction.php # Delete transaction (123 lines)
    get_transactions.php                    # List transactions (36 lines)
    print_additional_receipt.php            # Printable receipt (397 lines)
    update_additional_payment_base.php      # Update payment (701 lines)
    update_additional_payment_transaction.php # Update transaction (190 lines)
client/
  additional_payments.php                   # Client list (935 lines)
  additional_payments_detail.php            # Client detail (677 lines)
modals/additional_payment/
  add_payment_modal.php                     # Add form (100 lines)
  edit_payment_modal.php                    # Edit form (103 lines)
  add_transaction_modal.php                 # Transaction manager (211 lines)
  edit_transaction_modal.php                # Edit transaction (85 lines)
js/additional_payments/
  main.js                                   # Payment CRUD (787 lines)
  transactions.js                           # Transaction manager (552 lines)
tenant_super_admin/
  additional_payments.php                   # Cross-branch view (541 lines)
uploads/tutorials/additional_payment_tutorials/
  add-additional-paymanents-payment.png
  add-additional payments.png
```
