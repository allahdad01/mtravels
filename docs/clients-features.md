# Clients Module — Complete Feature Documentation

## Overview

Multi-tenant, branch-aware client management system with dual-currency balance tracking, regular/agency client types, full transaction lifecycle, funding operations, client-to-supplier JV payments, cross-module integration (ticket/visa/hotel/umrah), client portal access, and low-balance alerts.

---

## 1. Client CRUD

**Page:** `admin/client.php` | **API:** `api/client/`

| Action | API | Modal |
|--------|-----|-------|
| **Add** | `api/client/add_clients.php` | `modals/client/add_client.php` — name, email, phone, password (bcrypt hashed), address, USD balance, AFS balance, client_type (regular/agency), status. Logs to `activity_log`. |
| **Edit** | `api/client/update_client.php` | `modals/client/edit_client.php` — name, email, phone, address, client_type, status. |
| **Delete** | `api/delete_client.php` | Hard DELETE from `clients` table. |

**JS:** `js/client/client_management.js` (420 lines) — loads clients via API, renders stats bar, tabs (active/inactive), type filters, search, pagination (8/page), CRUD via SweetAlert2.

**Additional:** `assets/js/client-search.js` (180 lines) — card search and pagination by name/balance/status.

---

## 2. Client Detail Page

**Page:** `admin/client_detail.php` (1,263 lines)

| Section | Detail |
|---------|--------|
| **Info** | Name, email, phone, address, type, status |
| **Balances** | USD + AFS balances displayed |
| **Booking Counts** | Tickets, Visas, Hotels, Umrah totals |
| **Transaction History** | Paginated, filterable by type |
| **Financial Summary** | Per-type transaction breakdowns |
| **Cross-Links** | Links from ticket/visa/hotel/umrah detail pages |

---

## 3. Client Types: Regular vs Agency

| Aspect | `regular` | `agency` |
|--------|-----------|----------|
| **Balance tracking** | `usd_balance` / `afs_balance` updated on every transaction | Balance is informational only — no automatic updates |
| **Refund behavior** | `UPDATE clients SET balance = balance + amount` | Inserts `client_transactions` but does NOT update balance field |
| **Running balance** | Tracked in `client_transactions.balance` column | Set to `0` in transaction records |
| **Payment status** | No indicators on booking cards | Red (unpaid) / Yellow (partial) / Green (paid) indicators |
| **Booking form** | Balance auto-fetched and displayed | Balance shown as 0 or not fetched |

---

## 4. Client Funding (Make Payment)

**Modal:** `modals/accounts/client_payment_modal.php` (691 lines — 7-step wizard) | **API:** `api/accounts/fundClient.php` (384 lines)

| Step | Field |
|------|-------|
| 1 | Choose balance currency (USD/AFS) |
| 2 | Choose payment currency |
| 3 | Exchange rate (only when currencies differ) |
| 4 | Amount with live conversion preview |
| 5 | Select main account (from pre-loaded dropdown) |
| 6 | Receipt number |
| 7 | Optional remarks |

Creates: `client_transactions` (credit) + `main_account_transactions` (debit). Supports dual-currency with exchange rates.

---

## 5. Client Transaction History

**Modal:** `modals/accounts/client_transaction_history_modal.php` (118 lines) | **API:** `api/accounts/get_client_transactions.php` (171 lines)

- Paginated table with currency/receipt/date filters
- 19 LEFT JOINs to resolve `reference_id` across all modules (ticket_sale, ticket_reserve, ticket_refund, visa_sale, visa_refund, umrah, umrah_refund, hotel, hotel_refund, fund, jv_payment, additional_payment, weight_sale)
- Running balance columns (debit/credit/balance)
- Print/PDF export button
- Edit/delete actions gated by role

---

## 6. Transaction Reversal

**API:** `api/accounts/delete_client_transaction.php` (284 lines)

- Fetches transaction details
- Reverses client balance by the opposite amount
- Updates all SUBSEQUENT transactions' `balance` columns to maintain chain consistency
- Deletes the transaction record

---

## 7. Client-to-Supplier JV Payments

| Page | Purpose |
|------|---------|
| `admin/jv_payments.php` (1,073 lines) | Lists all JV payments with client dropdown |
| `admin/process_client_supplier_jv.php` | Creates JV: deducts client balance, credits supplier balance |
| `admin/process_client_supplier_jv_delete.php` (413 lines) | Reverses JV: reverses all related transactions |
| `admin/get_jv_payment.php` | Fetches single JV payment details for editing |

Dual-currency support. Creates: `client_transactions` (debit) + `supplier_transactions` (credit) + `jv_transactions`.

---

## 8. Status Toggle

**API:** `api/accounts/toggle_client_status.php`

- Toggles between `active` and `inactive`
- Rendered in accounts page via `js/accounts/status-management.js`

---

## 9. Low Balance Alerts

**API:** `api/dashboard/client_notification.php`

| Currency | Threshold |
|----------|-----------|
| USD | Balance <= -$1,000 |
| AFS | Balance <= -20,000 AFs |

Displayed as red danger banners on the Accounts page (`admin/accounts.php`).

---

## 10. Client Data APIs (for dropdowns)

| API | Used By |
|-----|---------|
| `api/client/getClients.php` | Main client management page |
| `api/ticket/get_client_info.php` | Ticket booking — name, type, phone |
| `api/ticket/get_client_balance.php` | Ticket booking — balance by currency |
| `api/ticket/getClientType.php` | Ticket booking — regular vs agency |
| `api/hotel/fetch_clients.php` | Hotel booking dropdowns |
| `admin/fetch_client_data.php` | AJAX helper for tickets/visas |

---

## 11. Module Integration Summary

| Module | Integration |
|--------|-------------|
| **Ticket** | `ticket_bookings.sold_to`, refunds create `client_transactions`, balance check on booking |
| **Visa** | `visa_applications.sold_to`, refunds create `client_transactions` |
| **Hotel** | `hotel_bookings.sold_to`, refunds create `client_transactions` |
| **Umrah** | `umrah_bookings.sold_to`, refunds create `client_transactions`, family member type check |
| **Additional Payments** | `additional_payments.client_id`, payment status for agency clients |
| **JV Payments** | Client-to-supplier direct payments |
| **Accounts** | Funding, transaction history, status toggle |
| **Dashboard** | Low-balance alerts, client balance display |
| **Messaging** | `messages.recipient_type = 'clients'`, `recipient_table = 'clients'` |
| **Client Portal** | Separate `client/` directory with dashboard, bookings, reports |

---

## 12. Database Tables

### `clients`
| Column | Type | Detail |
|--------|------|--------|
| id | int(11) PK | Auto-increment |
| tenant_id | int(11) FK | CASCADE on delete |
| name | varchar(255) | |
| email | varchar(255) | UNIQUE per tenant |
| password_hash | varchar(255) | Bcrypt hashed |
| phone | varchar(50) | |
| usd_balance | decimal(10,3) | |
| afs_balance | decimal(10,3) | |
| address | varchar(255) | |
| client_type | enum('regular','agency') | Default 'regular' |
| status | enum('active','inactive') | Default 'active' |
| totp_enabled | tinyint(1) | 2FA flag for client portal |
| branch_id | bigint(20) | |

### `client_transactions`
| Column | Type | Detail |
|--------|------|--------|
| id | int(11) PK | Auto-increment |
| client_id | int(11) FK | |
| type | enum('credit','debit') | |
| amount | decimal(15,3) | |
| balance | decimal(15,3) | Running balance after transaction |
| currency | enum('USD','AFS') | |
| transaction_of | enum(18+ values) | Source module type |
| reference_id | int(11) | Links to source module record |
| receipt | varchar(100) | |
| exchange_rate | decimal(10,5) | |

### Tables referencing `clients.id`
`ticket_bookings.sold_to`, `ticket_reservations.sold_to`, `visa_applications.sold_to`, `hotel_bookings.sold_to`, `umrah_bookings.sold_to`, `date_change_tickets.sold_to`, `refunded_tickets.sold_to`, `additional_payments.client_id`

---

## 13. Role & Permission Model

| Role | Access |
|------|--------|
| `admin` | Full CRUD, fund, toggle status, delete transactions, JV payments |
| `finance` | Full CRUD, fund, view transactions, JV payments |
| Others | No access to admin pages |
| `client` | Client portal only (`client/dashboard.php` etc.) |

**Feature gate:** Not feature-gated — available to all plans. Menu controlled by `staffCanSeeMenu()`.

---

## 14. Language Support

| Language | File |
|----------|------|
| English | `includes/languages/en/common.php` (~80+ keys) |
| Dari/Farsi | `includes/languages/fa/common.php` |
| Pashto | `includes/languages/ps/common.php` |

---

## 15. File Map

```
admin/
  client.php                              # Main client list page
  client_detail.php                       # Client detail dashboard (1,263 lines)
  fetch_client_data.php                   # AJAX: client tickets/visas
  jv_payments.php                         # Client-to-supplier JV list (1,073 lines)
  process_client_supplier_jv.php          # Create JV payment
  process_client_supplier_jv_delete.php   # Reverse JV payment (413 lines)
  get_jv_payment.php                      # Fetch single JV for edit
api/
  client/
    getClients.php                        # All clients JSON
    add_clients.php                       # CREATE
    update_client.php                     # UPDATE
  delete_client.php                       # DELETE
  accounts/
    fundClient.php                        # Fund from main account (384 lines)
    get_client_transactions.php           # Paginated history (19 JOINs)
    delete_client_transaction.php         # Reverse transaction (284 lines)
    toggle_client_status.php              # Status toggle
  dashboard/
    client_notification.php               # Low-balance alerts
  ticket/
    get_client_info.php                   # Name, type, phone
    get_client_balance.php                # Balance by currency
    getClientType.php                     # Regular vs agency
  hotel/
    fetch_clients.php                     # Hotel booking dropdowns
modals/
  client/
    add_client.php                        # Add modal
    edit_client.php                       # Edit modal
  accounts/
    client_payment_modal.php              # 7-step payment wizard (691 lines)
    client_transaction_history_modal.php  # History modal (118 lines)
js/
  client/
    client_management.js                  # Main CRUD (420 lines)
  accounts/
    status-management.js                  # Status toggle
    transaction-management.js             # Transaction actions
  ticket/
    client-phone-autofill.js              # Auto-fill phone on booking
assets/js/
  client-search.js                        # Card search (180 lines)
tenant_super_admin/
  clients.php                             # Cross-branch view (556 lines)
  get_client_transactions.php             # Transaction viewer (235 lines)
client/                                   # Client portal (~28 files)
  dashboard.php                           # Client dashboard (986 lines)
  ticket.php, hotel.php, visa.php, umrah.php, ...
includes/
  nav_items.php                           # Menu entry
  header_client.php                       # Client portal header (1,728 lines)
```
