# Ticket Module — Complete Feature Documentation

## Overview

The Ticket/Airline module manages the full lifecycle of airline ticket operations: issuance, reservations, date changes, refunds, excess baggage weight purchases, and a support/helpdesk ticket system. Multi-tenant and multi-branch.

---

## 1. Ticket Bookings (Issued Tickets)

**Admin page:** `admin/ticket.php` | **Client portal:** `client/ticket.php`

| Feature | Details |
|---------|---------|
| **KPI Strip** | 4 metric cards — total tickets, total sold amount, total profit, etc. |
| **Status Tabs** | All / Booked / Date Changed / Refunded |
| **Search** | By PNR, passenger name, airline |
| **Ticket Cards** | Color-coded by payment (green=paid, amber=partial, red=unpaid, blue=neutral). Shows PNR, client, passenger, route, airline, issue/departure/return dates/times, refund data, date change data, weight info, sold price |
| **Actions** | View details (4-tab modal), edit, manage transactions, delete |
| **Book Ticket** | Opens book ticket modal — supports PDF upload with auto-fill |
| **Multi-Ticket Invoice** | FAB button to generate combined invoice across selected tickets |
| **Pagination** | 10 per page |

### PDF Auto-Fill (Quick Import)
- Drop zone in booking modal accepts PDFs (max 10MB)
- `ticket_patterns.php` engine detects format and extracts: PNR, passenger names, airline, flight number, origin/destination, dates/times, cabin class, baggage, ticket number, issue date
- Supports TBO, Sirena, Amadeus, FlyDubai, Salaam Portal, Meraj Air, Hadita Portal, Kam Air, Ariana, Air Arabia, Skyportal, NSST Portal, and Standard formats

### Passenger Management
- Dynamic fields based on adult/child/infant counts
- Per-passenger: title (Mr/Mrs/Child), name, gender
- Round-trip vs one-way trip type toggle

---

## 2. Ticket Reservations (Pre-Book Holds)

**Admin page:** `admin/ticket_reserve.php` | **Client portal:** `client/ticket_reserve.php`

- Similar feature set to ticket bookings but for pre-booking holds
- Status defaults to `Reserved` until converted to a booking
- Dedicated reserve booking form, edit, view details, transaction management
- Multi-ticket reserve invoice generation
- Search by passenger name, PNR, airline, origin, destination, supplier, client

---

## 3. Ticket Weights (Excess Baggage)

**Admin page:** `admin/ticket_weights.php` | **Client portal:** `client/ticket_weights.php`

| Feature | Details |
|---------|---------|
| **Search** | By passenger name, PNR, phone, airline, city |
| **Weight Cards** | PNR, passenger, client, route, weight (kg), base/sold/profit prices, remarks |
| **Payment Status** | Paid/Partial/Unpaid/Neutral (for agency clients) |
| **Actions** | Manage transactions, edit weight, delete |
| **Add Weight** | Modal to attach excess baggage to an existing ticket |
| **Multi-Ticket Invoice** | Combined weight invoice across selected records |
| **Pagination** | 10 per page |

---

## 4. Ticket Refunds

**Admin page:** `admin/refund_ticket.php` | **Client portal:** `client/refund_ticket.php`

| Feature | Details |
|---------|---------|
| **Search** | By passenger name, PNR, phone, airline, city |
| **Refund Cards** | PNR, passenger + title, client, route, airline, base/sold prices, supplier penalty, service penalty, refund-to-passenger amount, currency |
| **Actions** | Manage transactions, print refund agreement, delete refund |
| **Add Refund** | Search by PNR/name → auto-populate base/sold → input penalties → auto-calculate refund amount (choose calculation from sold or from base) |
| **Multi-Ticket Invoice** | Combined refund invoice |
| **Print Refund Agreement** | Generates printable refund agreement document via `print_ticket_refund_agreement.php` |

---

## 5. Date Changes

**Modals:** `modals/ticket/ticket_date_change_modal.php`

| Feature | Details |
|---------|---------|
| **Date Type** | Departure, return, or both |
| **New Dates** | Select new departure and/or return date |
| **Penalties** | Supplier penalty, service penalty |
| **Auto-calc** | Refund amount recalculated from penalties |
| **Multi-Ticket Invoice** | Combined date change invoice |

---

## 6. Single Ticket Detail View

**Admin page:** `admin/ticket_detail.php?id=X` | **Reservation:** `admin/ticket_reservation_detail.php?id=X`

- Full ticket information display (all columns)
- Client info (name, email, phone)
- Supplier info (name, email, phone)
- Paid-to account info
- Refund data (from `refunded_tickets`)
- Date change data (from `date_change_tickets`)
- Weight data (from `ticket_weights`)
- **Three transaction tables:** Main Account, Client, Supplier
- **Action buttons:** Refund, Date Change, Add Weight, Edit, Delete

---

## 7. Support/Helpdesk Tickets

| Page | Purpose |
|------|---------|
| `admin/support_tickets.php` | List all support tickets with status/priority badges |
| `admin/support_ticket_create.php` | Create ticket — title, description, category, priority, screenshot upload |
| `admin/support_ticket_detail.php` | Full detail with reply thread, internal notes, status management |
| `admin/submit_ticket.php` | Minimal submission form |
| `admin/view_tickets.php` | Current user's tickets |
| `admin/view_ticket_details.php` | Alternate detail view |

### SLA System
- **Rules** defined per priority level in `ticket_sla_rules` table
- **On creation:** SLA due time calculated via `SLACalculator`
- **Hourly cron** (`cron/update_ticket_sla.php`) updates statuses: on_track → at_risk → breached
- **Notifications** dispatched via `TicketNotificationService` on breach
- Statuses supported: open, in_progress, resolved, closed
- Priorities: low, medium, high, critical

---

## 8. Database Tables (7 core tables)

| Table | Purpose |
|-------|---------|
| `ticket_bookings` | Core ticket sales (issued tickets) |
| `ticket_reservations` | Pre-booking holds |
| `ticket_weights` | Excess baggage purchases |
| `refunded_tickets` | Refund records with penalty tracking |
| `date_change_tickets` | Date change request records |
| `support_tickets` | Helpdesk tickets |
| `ticket_replies` | Support ticket replies/conversations |
| `ticket_notifications` | Notification logs for ticket events |
| `ticket_sla_rules` | SLA targets per priority |
| `ticket_categories` | Support ticket categories |

---

## 9. Technical Architecture

### Admin Pages: 12 files
`admin/ticket.php`, `admin/ticket_weights.php`, `admin/ticket_reserve.php`, `admin/refund_ticket.php`, `admin/ticket_detail.php`, `admin/ticket_reservation_detail.php`, `admin/support_tickets.php`, `admin/support_ticket_create.php`, `admin/support_ticket_detail.php`, `admin/submit_ticket.php`, `admin/view_tickets.php`, `admin/view_ticket_details.php`

### Modal Files: 24 files across 5 sub-systems
- `modals/ticket/` — booking, edit, details, refund, date change, weight, multi-ticket, transaction
- `modals/ticket_reserve/` — booking, edit, details, multi-ticket, transaction
- `modals/ticket_weight/` — booking, edit, multi-ticket, transaction
- `modals/ticket_refund/` — refund, transaction, edit_transaction, multi-ticket
- `modals/ticket_date_change/` — multi-ticket

### JavaScript: 40+ files
- `js/ticket/` — 23 files (form, details, PDF extraction, refund calc, profit calc, weight mgmt, search, transactions, multi-invoice, airline select, etc.)
- `js/ticket_reserve/` — 7 files
- `js/ticket_weight/` — 3 files
- `js/ticket_refund/` — 6 files
- `js/ticket/data/airlines.js` — 196 airlines database

### API Endpoints: 50+ files
- `api/ticket/` — 18 files (CRUD, search, PDF extract, payments, multi-invoice)
- `api/ticket_reserve/` — 9 files
- `api/ticket_weight/` — 2 files
- `api/ticket_refund/` — 11 files
- `api/ticket_date_change/` — 8 files
- `api/dashboard/` — 2 files

### Client Portal: 7 files
`client/ticket.php`, `client/ticket_detail.php`, `client/ticket_weights.php`, `client/ticket_reserve.php`, `client/ticket_reservation_detail.php`, `client/refund_ticket.php`, `client/handlers/ticket_handler.php`

### Includes: 5 files
`includes/ticket_patterns.php` (PDF extraction engine with 200+ airport lookup), `includes/SupportTicketManager.php`, `includes/SLACalculator.php`, `includes/TicketNotificationService.php`, `includes/SecureFileUpload.php`

### CSS: 5 files
`css/ticket/ticket_styles.css`, `css/ticket/ticket-form.css`, `css/ticket/ticket-components.css`, `css/ticket/ticket_weight.css`, `css/ticket/refund_ticket_styles.css`

### Cron: 1 file
`cron/update_ticket_sla.php` — hourly SLA status update

### Super Admin: 3 files
`tenant_super_admin/ticket_weights.php`, `tenant_super_admin/ticket_reservations.php`, `tenant_super_admin/ticket_bookings.php`

---

## 10. Key Technical Details

- **Payment Status Calculation** — For agency clients, payment status (paid/partial/unpaid/neutral) calculated dynamically from `main_account_transactions` with currency conversion
- **Multi-Tenant Isolation** — All queries filtered by `tenant_id` and `branch_id` using prepared statements
- **Agency vs Direct Clients** — Agency clients get full transaction management; direct clients show neutral status
- **Invoice Generation** — FAB on booking/reserve/weight pages generates combined PDF invoice for selected items
- **Ticket Patterns Engine** — Pattern-matching parser in `ticket_patterns.php` with airport/airline lookup tables for extracting data from multiple airline e-ticket formats
- **Secure File Upload** — Validated by `SecureFileUpload.php` (type, size, path traversal prevention)
- **SLA Cron** — Hourly job updates support ticket SLA statuses and sends breach notifications
