# Suppliers Module — Complete Feature Documentation

## Overview

Multi-tenant, branch-aware supplier management system with full CRUD, categorized visibility (ticket/visa/umrah/hotel/all), Internal/External types, balance tracking, multi-currency funding/withdrawals/bonuses, penalties, client-to-supplier JV payments, quarterly tax reporting, low-balance alerts, and deep integration with every revenue module.

---

## 1. Supplier CRUD

**Page:** `admin/supplier.php` | **API:** `api/supplier/`

| Action | API | Modal |
|--------|-----|-------|
| **Add** | `api/supplier/add_supplier.php` | `modals/supplier/add_supplier.php` — name, contact_person, phone, email, address, currency (USD/AFS), balance, type (Internal/External), category (ticket/visa/umrah/hotel/all), status. Logs to `activity_log`. |
| **Edit** | `api/supplier/update_supplier.php` | `modals/supplier/edit_supplier.php` — same fields pre-populated. Logs to `activity_log`. |
| **Delete** | `api/supplier/delete_supplier.php` | Hard DELETE from `suppliers` table. Logs to `activity_log`. |
| **Activate** | `api/supplier/activate_supplier.php` | Sets `status = 'active'` |
| **Deactivate** | `api/supplier/deactivate_supplier.php` | Sets `status = 'inactive'` |

**JS:** `js/supplier/supplier_management.js` — full CRUD, search, pagination (10/page), tab switching (active/inactive), activate/deactivate/delete/edit.

---

## 2. Supplier Detail Page

**Page:** `admin/supplier_detail.php`

- Profile info display
- KPI cards: Total Credit, Total Debit, Current Balance, Transaction Count
- Full transaction table with pagination

---

## 3. Category System

| Category | Visible In Modules |
|----------|-------------------|
| `ticket` | Ticket booking, reservation, refund, date change, weights |
| `visa` | Visa applications, refund |
| `umrah` | Umrah bookings, services, refund |
| `hotel` | Hotel bookings, refund |
| `all` | All modules |

---

## 4. Supplier Types: Internal vs External

| Aspect | Internal | External |
|--------|----------|----------|
| **Balance tracking** | Yes | Yes |
| **Main account funding** | Required on transaction creation | Depends on flow |
| **Funding/Withdraw actions** | Hidden in accounts page (`admin/accounts.php` line 845) | Full fund/bonus/withdraw buttons |
| **Refund balance update** | Both get `supplier_transactions` record; External also updates `suppliers.balance` | Full balance adjustment |

---

## 5. Financial Operations (from Accounts Page)

**Page:** `admin/accounts.php` — Supplier Accounts section

### 5.1 Fund Supplier
**Modal:** `modals/accounts/fund_supplier_modal.php` (110 lines) | **API:** `api/accounts/fund_supplier.php`
- Select source main account (loaded dynamically)
- Payment currency toggle (USD/AFS), exchange rate if currencies differ
- Amount, receipt number, remarks
- Creates: `supplier_transactions` (credit) + `main_account_transactions` (debit)
- Sends notification

### 5.2 Supplier Bonus
**Modal:** `modals/accounts/bonus_supplier_modal.php` (77 lines) | **API:** `api/accounts/add_supplier_bonus.php`
- Credits supplier balance without debiting a main account
- Creates `supplier_transactions` with `transaction_of = 'supplier_bonus'`

### 5.3 Withdraw from Supplier
**Modal:** `modals/accounts/withdraw_supplier_modal.php` (75 lines) | **API:** `api/accounts/withdraw_fund.php`
- Debits supplier balance, credits main account

### 5.4 Toggle Status
**API:** `api/accounts/toggle_supplier_status.php` | **JS:** `js/accounts/status-management.js`

### 5.5 Delete Transaction
**API:** `api/accounts/delete_supplier_transaction.php` — reverses transaction + adjusts balance

### 5.6 Supplier Transaction History
**Modal:** `modals/accounts/supplier_transaction_history_modal.php` (101 lines) | **API:** `api/accounts/get_supplier_transactions_main.php`
- Paginated, filterable by receipt and date range
- Complex query joining 12+ transaction types for reference names
- Print/PDF support via `js/accounts/printing.js`

---

## 6. Client-to-Supplier JV Payments

**Page:** `admin/process_client_supplier_jv.php` | **Delete:** `admin/process_client_supplier_jv_delete.php`

- Direct payment from client balance to supplier balance (bypasses main account)
- Full reverse/delete support

---

## 7. Penalties (Supplier Deductions)

| Module | File | Detail |
|--------|------|--------|
| **Ticket Refund** | `modals/ticket_refund/refund_ticket_modal.php` | `supplier_penalty` field |
| **Ticket Date Change** | `modals/ticket_date_change/add_date_change.php` | `supplier_penalty` field |
| **Visa Refund** | `modals/visa/refund_modal.php` | `supplier_penalty` + `service_penalty` |
| **Hotel Refund** | `modals/hotel/refund_modal.php` | `supplier_penalty` field |
| **Umrah Refund** | `api/umrah/process_umrah_refund.php` | Multi-supplier penalty handling |

---

## 8. Legacy Endpoints

**Page:** `admin/get_supplier_transactions.php` — returns `funding_transactions` by date range
**Page:** `admin/get_supplier_transactions_filter.php` — union of `supplier_transactions` + `funding_transactions`

---

## 9. Supplier Data APIs (for dropdowns)

| API | Used By |
|-----|---------|
| `api/supplier/get_suppliers.php` | Returns suppliers + main account for forms |
| `api/supplier/getSupplier.php` | Full supplier list, ordered by name |
| `api/supplier/fetch_supplier_by_id.php` | Single supplier by ID |
| `admin/ajax/get_suppliers.php` | Simple JSON `{id, name, currency}` for dropdowns |
| `api/ticket/get_supplier_currency.php` | Ticket module currency fetch |
| `api/ticket/get_supplier_balance.php` | Ticket module balance fetch |
| `api/ticket_reserve/get_supplier_currency.php` | Ticket reservation currency fetch |
| `api/visa/get_supplier_currency.php` | Visa module currency fetch |
| `api/umrah/get_suppliers.php` | Umrah module — `category IN ('umrah', 'all')` |
| `api/umrah/get_supplier_type.php` | Internal/External check for Umrah transactions |
| `api/umrah/get_family_supplier_type.php` | Internal/External check for family Umrah |
| `api/hotel/fetch_suppliers.php` | Hotel bookings supplier list |
| `api/hotel/fetch_supplier_by_id.php` | Hotel booking edit — single supplier |

---

## 10. Module Integration Summary

| Module | Integration |
|--------|-------------|
| **Ticket** | Supplier selection in booking, currency/balance fetch, penalties in refund/date-change |
| **Visa** | Supplier selection in applications, penalties in refund |
| **Hotel** | Supplier selection in bookings, penalties in refund |
| **Umrah** | Multi-supplier service rows, Internal/External type check, penalties in refund |
| **Additional Payments** | `is_from_supplier` checkbox, `supplier_id` dropdown |
| **JV Payments** | Client-to-supplier direct payment |
| **Accounts** | Fund/bonus/withdraw/toggle/transactions |
| **Dashboard** | Low-balance notification (USD < 500, AFS < 20,000) |
| **Reports** | Quarterly tax report per supplier with PDF/Excel export |
| **Notifications** | `transaction_type = 'supplier'` in header notifications |

---

## 11. Database Tables

### `suppliers`
| Column | Type | Detail |
|--------|------|--------|
| id | int(11) PK | Auto-increment |
| tenant_id | int(11) FK | CASCADE on delete |
| name | varchar(255) | |
| contact_person | varchar(255) | |
| supplier_type | enum('Internal','External') | Default 'External' |
| phone | varchar(15) | |
| email | varchar(255) | |
| address | mediumtext | |
| currency | enum('USD','AFS') | |
| balance | decimal(10,3) | Running balance |
| category | varchar(100) | ticket/visa/umrah/hotel/all |
| status | enum('active','inactive') | Default 'active' |
| branch_id | bigint(20) | |

### `supplier_transactions`
| Column | Type | Detail |
|--------|------|--------|
| id | int(11) PK | Auto-increment |
| supplier_id | int(11) FK | |
| reference_id | int(100) | Links to source module record |
| transaction_type | enum('Debit','Credit') | |
| transaction_of | enum(21 values) | Source module type |
| amount | decimal(10,3) | |
| balance | decimal(15,3) | Running balance after transaction |
| receipt | varchar(100) | |
| branch_id | bigint(20) | |

### Related Tables (FK references)
`ticket_bookings.supplier`, `visa_applications.supplier`, `hotel_bookings.supplier_id`, `umrah_bookings.supplier_id`, `umrah_booking_services.supplier_id`, `additional_payments.supplier_id`, `funding_transactions.supplier_id`, `jv_transactions.supplier_id`, `date_change_umrah.supplier`, `tax_reports.supplier_id`

---

## 12. Role & Permission Model

| Role | Access |
|------|--------|
| `admin` | Full CRUD, fund/bonus/withdraw, toggle status, delete transactions |
| `finance` | Full CRUD, fund/bonus/withdraw, view transactions |
| Others | No access (redirect to login) |

**Feature gate:** `supplier_management` under `business_operations` in pricing-helper.php.

---

## 13. Language Support

| Language | File |
|----------|------|
| English | `includes/languages/en/common.php` |
| Dari/Farsi | `includes/languages/fa/common.php` (~50+ keys) |
| Pashto | `includes/languages/ps/common.php` |

---

## 14. Super Admin Page

**Page:** `tenant_super_admin/suppliers.php` | **AJAX:** `tenant_super_admin/get_supplier_transactions.php`

- Cross-branch supplier list with stats
- Detail modal + paginated transaction modal

---

## 15. File Map

```
admin/
  supplier.php                              # Main CRUD page
  supplier_detail.php                       # Single supplier detail
  accounts.php                              # Supplier accounts section (fund/bonus/withdraw)
  get_supplier_transactions.php             # Legacy endpoint
  get_supplier_transactions_filter.php      # Legacy endpoint
  fetch_supplier_currency.php               # Currency fetch
  process_client_supplier_jv.php            # Client-to-supplier JV payment
  process_client_supplier_jv_delete.php     # Reverse JV payment
  quarterly_tax_report.php                  # Per-supplier tax reporting
admin/ajax/
  get_suppliers.php                         # Simple dropdown JSON
api/supplier/
  get_suppliers.php                         # Suppliers + main account
  getSupplier.php                           # Full supplier list
  fetch_supplier_by_id.php                  # Single supplier
  add_supplier.php                          # CREATE
  update_supplier.php                       # UPDATE
  delete_supplier.php                       # DELETE
  activate_supplier.php                     # Set active
  deactivate_supplier.php                   # Set inactive
api/accounts/
  fund_supplier.php                         # Fund from main account
  get_supplier_transactions_main.php        # Paginated history (12+ types)
  toggle_supplier_status.php                # Status toggle
  add_supplier_bonus.php                    # Bonus credit
  delete_supplier_transaction.php           # Delete transaction
api/dashboard/
  supplier_notification.php                 # Low-balance alerts
api/ticket/
  get_supplier_currency.php
  get_supplier_balance.php
api/ticket_reserve/
  get_supplier_currency.php
api/visa/
  get_supplier_currency.php
api/umrah/
  get_suppliers.php
  get_supplier_type.php
  get_family_supplier_type.php
api/hotel/
  fetch_suppliers.php
  fetch_supplier_by_id.php
modals/supplier/
  add_supplier.php                          # Add modal
  edit_supplier.php                         # Edit modal
modals/accounts/
  fund_supplier_modal.php                   # Fund modal
  withdraw_supplier_modal.php               # Withdraw modal
  bonus_supplier_modal.php                  # Bonus modal
  supplier_transaction_history_modal.php    # History modal
js/supplier/
  supplier_management.js                    # Main CRUD JS
js/accounts/
  printing.js                               # Print transaction history
  transaction-management.js                 # Transaction actions
tenant_super_admin/
  suppliers.php                             # Cross-branch view
  get_supplier_transactions.php             # Transaction viewer
uploads/tutorials/
  supplier_tutorials/                       # Tutorial images
  accounts_tutorials/                       # Supplier-related account tutorials
```
