# Hotel Module — Complete Feature Documentation

## Overview

The Hotel Management module handles the full lifecycle of hotel/accommodation bookings: creation, editing, payment tracking, refunds, multi-booking invoices, and client portal access. Multi-tenant and multi-branch.

---

## 1. Booking Management

**Admin page:** `admin/hotel.php` | **Client portal:** `client/hotel.php`

| Feature | Details |
|---------|---------|
| **Create Booking** | 6-section form: Guest Info (title, name, gender), Booking Details (order ID, issue date, contact), Stay Details (check-in/out, accommodation text), Financial Details (base amount, sold amount, auto-calculated profit), Additional Details (supplier, client, account, currency, remarks) |
| **Edit Booking** | Pre-populated edit form with same section structure, profit auto-recalculates on amount change |
| **View Booking** | Inline modal with full details: guest info, stay details, financial summary, parties, remarks |
| **Delete Booking** | Confirmation dialog with AJAX delete and CSRF protection |
| **Search** | By order ID, guest name, contact number, accommodation details |
| **Filter Tabs** | All / Confirmed / Pending / Cancelled |
| **Pagination** | Server-side, 10 per page with "Showing X-Y of Z" display |
| **Night Calculation** | Auto-computed check-in/out date difference shown as "X nights" pill |
| **Payment Status** | Paid / Partial / Unpaid / Neutral (for agency clients, calculated from transactions) |
| **Statuses** | `active` (confirmed), `pending`, `refunded` |

---

## 2. Financial & Transaction Management

**Modal:** `modals/hotel/transaction_modal.php` | **JS:** `js/hotel/transactions.js`

| Feature | Details |
|---------|---------|
| **Multi-Currency** | USD and AFS (Afghani) supported throughout |
| **Exchange Rate System** | Dynamic field when payment currency differs from booking currency; supports USD, AFS, EUR, AED |
| **Transaction History** | Per-booking table: date, description, receipt number, amount, exchange rate, actions |
| **Paid/Remaining Amounts** | Calculated per currency and displayed in real-time |
| **Receipt Printing** | Separate print page via `print_hotel_receipt.php` |
| **Profit Tracking** | Auto-calculated as sold_amount - base_amount, displayed per booking row |
| **Edit/Delete Transactions** | Full CRUD on payment records |

---

## 3. Refund Management

**Admin page:** `admin/hotel_refunds.php` | **Modals:** `modals/hotel/refund_modal.php`

| Feature | Details |
|---------|---------|
| **Full Refund** | Refunds entire sold amount, sets profit to zero, status → refunded |
| **Partial Refund** | Proportional refund with partial profit recalculation |
| **Client Credit** | Regular clients get balance credited; agency clients get credit transaction recorded |
| **Supplier Credit** | Supplier balance credited for external suppliers |
| **Refund Records** | Stored in `hotel_refunds` table with type, amount, reason, processed flag |
| **Actions** | View booking, view transaction, process payment, print agreement, delete refund |
| **Refund Processing** | `processed` flag tracks whether refund is fully financially processed |
| **Refund List** | Paginated table of all refunds with search and actions |

---

## 4. Multi-Booking Invoice Generation

**Modal:** `modals/hotel/multi_ticket.php` | **JS:** `js/hotel/invoices.js`

- **Combined Invoice** — Select multiple bookings and generate a single combined invoice
- **FAB (Floating Action Button)** — Quick access from the bookings page
- **Invoice Features** — Agency branding, client info, booking table (guest name, order ID, stay period, accommodation, amount, paid), total amount and total nights, bank account details (USD/AFS), signature boxes, print button
- **Bulk Selection** — Select All checkbox

---

## 5. Single Booking Detail View

**Admin page:** `admin/hotel_detail.php?id=X` | **Client portal:** `client/hotel_detail.php`

- Guest info (name, title, gender, contact)
- Booking info (order ID, issue date, supplier, client, paid-to)
- Financial info (base amount, sold amount, profit, currency)
- Stay details (check-in/out, accommodation, nights)
- **Three transaction tables:** Main Account, Client, Supplier
- **Action buttons:** Edit, Refund, Delete

---

## 6. Notifications

- **Email** — `sendHotelNotification()` sends booking confirmation to client email on creation
- **WhatsApp** — `WhatsAppManager` sends booking notification (non-blocking)
- **Activity Log** — All operations logged with old/new values, IP address, user agent

---

## 7. Client Portal

| Page | Purpose |
|------|---------|
| `client/hotel.php` | Booking list with summary cards (total bookings, spent, nights, confirmed, pending, cancelled) and urgency indicators (Today/Soon/Completed) |
| `client/hotel_detail.php` | Single booking detail with client transactions |
| `client/hotel_refunds.php` | Refund list with summary stats and processed/pending split |

---

## 8. Tenant Super Admin

**Page:** `tenant_super_admin/hotel_bookings.php`

- Cross-branch view of all hotel bookings
- Branch filter dropdown
- Details modal with Summary / Accommodation tabs
- Financial summary strip (Sold Amount, Base Amount, Profit)

---

## 9. Supplier & Client Integration

| Feature | Details |
|---------|---------|
| **Supplier Selection** | Dynamic dropdown via AJAX, auto-loads supplier currency |
| **Supplier Balance** | External supplier balances debited on booking, credited on refund |
| **Client Selection** | Dynamic dropdown via AJAX |
| **Regular Clients** | Balance deducted on booking, credited on refund |
| **Agency Clients** | Payment managed separately via transactions, no balance deduction |
| **Agency Payment Status** | Computed from `main_account_transactions`: Paid / Partial / Unpaid |

---

## 10. Database Tables

| Table | Purpose |
|-------|---------|
| `hotel_bookings` | Core hotel bookings with full guest/stay/financial info |
| `hotel_refunds` | Refund records with type, amount, reason, processed flag |

---

## 11. Technical Architecture

### Admin Pages: 3 files
`admin/hotel.php`, `admin/hotel_detail.php`, `admin/hotel_refunds.php`

### Client Portal: 3 files
`client/hotel.php`, `client/hotel_detail.php`, `client/hotel_refunds.php`

### Super Admin: 1 file
`tenant_super_admin/hotel_bookings.php`

### Modal Files: 9 files
- `modals/hotel/` — add, edit, view details, transaction, edit transaction, refund, multi-ticket
- `modals/hotel_refund/` — transaction, edit transaction

### JavaScript: 10 files
- `js/hotel/` — init, bookings, transactions, refunds, refund_modal, invoices, toast, extra
- `js/hotel_refund/` — hotel_management, transaction_manager

### API Endpoints: 30 files under `api/hotel/`

| Category | Count | Key Endpoints |
|----------|-------|--------------|
| Booking CRUD | 6 | `add_hotel_booking.php`, `update_hotel_booking.php`, `delete_hotel_booking.php`, `get_hotel_bookings.php`, `get_hotel_booking.php`, `fetch_hotel_bookings.php` |
| Transactions | 11 | `add_hotel_transaction.php`, `update_hotel_transaction.php`, `update_refund_hotel_transaction.php`, `delete_hotel_transaction.php`, `get_hotel_transactions.php`, `get_hotel_bookings_tran.php`, `refund_hotel_transaction.php`, `add_hotel_refund_transactoin.php`, `delete_hotel_refund_transactions.php`, `print_hotel_receipt.php`, `print_hotel_refund_receipt.php` |
| Refunds | 3 | `process_hotel_refund.php`, `get_hotel_refund_details.php`, `get_hotel_refund_transactions.php`, `get_hotel_refund_transaction.php`, `delete_hotel_refund.php` |
| Invoices | 2 | `generate_multi_hotel_invoice.php`, `fetch_hotels_for_invoice.php` |
| Dropdowns | 4 | `fetch_suppliers.php`, `fetch_supplier_by_id.php`, `fetch_clients.php`, `fetch_main_accounts.php` |
| Shared | 1 | `hotel_handler.php` |

### Feature Flags
Controlled by `hasFeature()` for `hotel_bookings` and `hotel_refunds`

### Multilingual
Languages: English, Dari (fa), Pashto (ps) with translation keys for all UI strings
