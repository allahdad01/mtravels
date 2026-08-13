Yes. Now that we have locked the rule that Family is only a grouping point for Members, I would make the implementation plan much more precise.

The original discussion gives us the major architecture, but below I am turning it into a proper construction roadmap, in the order I would actually build the system.

MTravels Umrah — Professional Step-by-Step Construction Plan
PHASE 0 — Freeze the business rules

Before creating tables or changing PHP, establish these rules.

Rule 1 — Family

Family is only:

A gathering/grouping point for members.

Family
   │
   ├── Member
   ├── Member
   ├── Member
   └── Member

Family does not own:

payments
prices
services
fulfillment
suppliers
supplier costs
profit

Those belong to the individual member/booking.

Rule 2 — Member

The member is the actual commercial customer.

Member
   ↓
Umrah Booking
   ↓
Package
   ↓
Sold Services
   ↓
Payments
   ↓
Fulfillment
Rule 3 — Package

A package is a reusable definition of what we sell.

Rule 4 — Sold Service

A sold service represents something actually sold to a particular member.

Rule 5 — Fulfillment

Fulfillment represents how MTravels actually provides that sold service.

Rule 6 — Supplier

Supplier is generic and can provide any combination of:

Flight
Visa
Hotel
Transport
Meal
etc.

The original discussion explicitly arrives at this generic supplier model.

Rule 7 — Selling price ≠ supplier cost

Always keep:

Selling Price

separate from:

Supplier Cost
Rule 8 — Historical transactions must not change

Once a member buys a package, later package-price changes must not alter the member's existing price.

PHASE 1 — Study the existing system

Do not code yet.

Before touching the current application, inspect:

admin/umrah.php

and everything it currently depends on.

We need to identify:

existing member tables
existing family tables
existing payment tables
existing Umrah tables
existing statuses
existing authentication
existing users
existing supplier tables, if any
existing hotel tables, if any
existing transaction logic
existing JavaScript/AJAX
existing database conventions
Goal

Produce a map:

CURRENT SYSTEM
      │
      ├── Existing Family
      ├── Existing Member
      ├── Existing Payment
      ├── Existing Umrah
      └── Existing Admin/UI

Then determine what can be reused and what needs to be extended.

We should not replace existing working functionality unnecessarily.

PHASE 2 — Design the database architecture

This is the most important phase.

Before writing application code, finalize the relationships.

The core structure should become:

FAMILY
   │
   └── MEMBERS
          │
          └── BOOKINGS
                 │
                 ├── PACKAGE
                 │
                 ├── PAYMENTS
                 │
                 └── SOLD SERVICES
                        │
                        └── FULFILLMENT
                               │
                               ├── SUPPLIER
                               ├── RESOURCE
                               ├── CONTRACT
                               └── COST

Configuration exists separately:

SERVICES
   │
PACKAGES
   │
PACKAGE SERVICES
   │
PACKAGE CONFIGURATION

Hotel infrastructure exists separately:

HOTELS
   │
   ├── ROOM TYPES
   ├── ROOMS
   ├── CONTRACTS
   │      ├── INVENTORY
   │      └── RATES
   │
   └── ON-DEMAND
PHASE 3 — Build the Service Master

Create the service foundation.

Conceptually:

umrah_services

Initial services:

FLIGHT
VISA
HOTEL
TRANSPORT
MEAL
ZIYARAT

Each service should have:

id
name
code
service_type
is_active
created_at
updated_at

This avoids hardcoding services throughout the application, as recommended in the original design.

PHASE 4 — Build Packages

Create:

umrah_packages

A package might be:

Standard Umrah
Premium Umrah
VIP Umrah

Package fields should include the normal lifecycle information:

id
tenant_id
name
description
status
created_by
created_at
updated_at

The important point:

Package is configuration, not a customer's transaction.

PHASE 5 — Build Package Services

Create:

umrah_package_services

Example:

Standard Umrah
│
├── Flight       Required
├── Visa         Required
├── Hotel        Required
├── Transport    Required
└── Meal         Optional

Fields should cover things such as:

package_id
service_id
is_required
is_optional
sort_order

This establishes exactly what each package contains.

PHASE 6 — Build package-specific configuration

Now handle information that belongs specifically to a package/service.

For Hotel:

Standard Umrah
│
└── Hotel
     ├── Makkah → 5 nights
     └── Madinah → 4 nights

Create the appropriate package accommodation structure.

This allows the package to define:

city
nights
sequence
room requirement
other accommodation rules

without hardcoding them into the member booking.

Later we can do similar configuration for:

flight
visa
transport
meals

where necessary.

PHASE 7 — Build the customer price engine

Now determine:

What does MTravels sell each service for?

Example:

Standard Umrah

Flight             $500
Visa               $100
Makkah Hotel       $400
Madinah Hotel      $350
Transport          $100
--------------------------------
Package Total     $1,450

Optional:

Breakfast           +$50
Full Board         +$180

This is the selling-price side.

It must not be mixed with supplier costs.

PHASE 8 — Build price versions

Don't simply overwrite old prices.

Instead:

PRICE VERSION 1
Makkah Hotel = $400

Later:

PRICE VERSION 2
Makkah Hotel = $450

Existing bookings continue using their original snapshot.

This is essential for reliable accounting.

PHASE 9 — Build Member + Booking integration

Now connect the package system to the existing Add Member process.

The new flow:

ADD MEMBER
    ↓
Personal Information
    ↓
Select Package
    ↓
System loads package
    ↓
Display services
    ↓
Display package price
    ↓
Select optional services
    ↓
Confirm

The original discussion recommends modifying the existing Add Umrah flow only after the backend foundation is established.

PHASE 10 — Generate Sold Services

When the member saves:

Member: Ahmad
Package: Standard Umrah

the system creates:

Sold Services
│
├── Flight
├── Visa
├── Makkah Hotel
├── Madinah Hotel
└── Transport

Each sold service gets its own:

selling price
status
dates/configuration where applicable
reference to the member/booking
snapshot information

This is one of the most important pieces of the architecture.

PHASE 11 — Build Member Payments

Payments belong to the member/booking, never to the Family.

Example:

Ahmad
Total: $1,450
Paid:    $800
Due:     $650

Another member in the same family can have:

Fatima
Total: $1,200
Paid:    $500
Due:     $700

The Family simply contains both members.

It does not own those payments.

PHASE 12 — Build the generic Supplier system

Create the supplier foundation:

suppliers

Then:

supplier_services

Example:

ABC Travel
✓ Flight
✓ Visa
✓ Hotel
✓ Transport
✓ Meals

Another supplier:

XYZ Hotel Supplier
✗ Flight
✗ Visa
✓ Hotel
✗ Transport

This follows the supplier capability model in the discussion.

PHASE 13 — Build supplier rates

Supplier costs must be service-specific.

Example:

ABC Travel

Flight
$450

Visa
$85

Makkah Hotel
SAR 400/night

Transport
$100

Not:

ABC Travel
Total Cost = $1,000

because we need service-level profitability.

PHASE 14 — Build Hotel Management

Now create the hotel subsystem.

14.1 Hotels
hotels
14.2 Cities
cities
14.3 Room Types
hotel_room_types

Examples:

Double
Triple
Quad
Suite
14.4 Rooms
hotel_rooms

Example:

Hotel A

501 → Double
502 → Double
503 → Triple
504 → Quad

The rooms are permanent hotel resources, not contract records.

PHASE 15 — Build Hotel Contracts

Create:

hotel_contracts

Example:

Hotel A
Contract CT-2026-004

01 Sep 2026
to
30 Oct 2026

Contract scope could be:

Specific Rooms
Floor
Multiple Floors
Entire Hotel
PHASE 16 — Build Contract Inventory

Connect contracts to rooms:

hotel_contract_inventory

Example:

Contract CT-004

501
502
503
504
505

with validity dates.

Now the system knows:

Which rooms MTravels actually controls under this contract?

PHASE 17 — Build Contract Rates

Create:

hotel_contract_rates

Example:

Double → SAR 400/night
Triple → SAR 550/night
Quad   → SAR 650/night

These are MTravels' procurement costs, not customer prices.

PHASE 18 — Build On-Demand Hotel Procurement

When contracted inventory is unavailable:

Contract Inventory
       ↓
No availability
       ↓
Search On-Demand
       ↓
Hotel B
Supplier B
SAR 500/night

Therefore hotel fulfillment supports:

CONTRACT

or:

ON_DEMAND

as described in the original plan.

PHASE 19 — Build Fulfillment

This is where the system becomes operational.

Every sold service eventually gets fulfilled.

Sold Service
     ↓
Fulfillment
     ↓
Supplier
     ↓
Cost
     ↓
Status

For Hotel:

Sold Hotel
    ↓
Hotel Fulfillment
    ├── Hotel
    ├── Room
    ├── Supplier
    ├── Contract
    ├── Dates
    ├── Rate
    └── Status

For Flight:

Sold Flight
    ↓
Flight Fulfillment
    ├── Supplier
    ├── Ticket
    ├── Flight details
    ├── Cost
    └── Status

And similarly for Visa, Transport and Meal.

PHASE 20 — Implement supplier assignment

Staff should be able to assign:

Flight → Supplier A
Visa → Supplier A
Makkah Hotel → Supplier B
Madinah Hotel → Supplier B
Transport → Supplier A
Meals → Supplier C

But also provide:

Assign Supplier to All

for the case where one supplier handles the entire Umrah.

Individual services can then be overridden.

The discussion explicitly recommends this flexibility.

PHASE 21 — Capture supplier-cost snapshots

When fulfillment is confirmed, save the actual cost used.

Example:

Makkah Hotel

Selling Price: $600

Supplier: ABC
Rate: SAR 400/night
Nights: 5

Supplier Cost:
SAR 2,000

If converted:

Exchange Rate:
0.266

Cost:
$532

Then:

Gross Profit:
$600 - $532 = $68

The important thing is that later changes to supplier rates don't rewrite historical transactions.

PHASE 22 — Build service statuses

Use controlled statuses.

Generic
Pending
Assigned
Requested
Confirmed
Completed
Cancelled
Hotel
Not Assigned
Reserved
Confirmed
Checked In
Checked Out
Cancelled
Visa
Not Applied
Applied
Processing
Issued
Rejected
Flight
Not Ticketed
Reserved
Ticketed
Changed
Cancelled

The original discussion proposes this service-specific approach.

PHASE 23 — Build the Member Dashboard

The member page should become the operational center.

AHMAD MUHAMMADI

Package
Standard Umrah

Financial
--------------------
Selling     $1,450
Paid          $800
Due           $650

Services
--------------------

Flight
Ticketed
Supplier: XYZ
Cost: $480

Visa
Issued
Supplier: ABC
Cost: $90

Makkah Hotel
5 Nights
Hotel A
Room 501
Confirmed
Supplier: ABC

Madinah Hotel
4 Nights
Hotel B
Room 204
Confirmed

Transport
Confirmed

The original discussion recommends turning the member view into this kind of centralized operational page.

PHASE 24 — Build the Hotel Dashboard — DONE

Create a dedicated hotel-management area.

Hotels
Contracts
Inventory
Bookings
On-Demand
Calendar

Dashboard example:

Makkah Hotel A

Double
10 Total
6 Available
3 Reserved
1 Occupied

Triple
6 Total
4 Available
2 Reserved

Delivered (admin/umrah_hotels.php, tabs: Overview / Hotels / Rooms / Contracts / Calendar):
- get_hotel_dashboard.php — stats, per-hotel × room-type occupancy matrix, recent stays, enriched contracts (names, inventory, rates), room counts per hotel/type.
- save_hotel.php — hotel / room_type / room CRUD with referential guards (delete/toggle allowed without full payload).
- save_contract.php — contract CRUD + replace inventory rooms + rates (validates rooms belong to the hotel).
- occupancy_helper.php — shared A/R/O/B grid engine (check-out day free; maintenance = B).

PHASE 25 — Build Hotel Calendar — DONE

Show:

Room     10 11 12 13 14 15

501       A  A  R  R  R  A
502       A  R  R  R  R  A
503       R  R  R  R  R  A

Where:

A = Available
R = Reserved
O = Occupied
B = Blocked

This becomes a major operational tool.

Delivered (Calendar tab in admin/umrah_hotels.php):
- get_hotel_calendar.php — room × date grid for a hotel, optional room-type filter, 90-day cap, tenant scoped.
- States: O = stay with assigned room; R = stay at room-type level; B = maintenance; A = free.
- E2E-verified: O/R/A/B grid + dashboard occupancy matrix + CRUD + cleanup (umrah_hotels_driver.php).

PHASE 26 — Build Finance

Once the above works, financial calculations become straightforward.

For every member:

Total Selling
- Total Supplier Cost
= Gross Profit

Then calculate:

Paid
Due

separately.

For every service:

Flight Revenue
Flight Cost
Flight Profit
Hotel Revenue
Hotel Cost
Hotel Profit

etc.

DONE — admin/umrah_finance.php (Finance tab) + get_finance_report.php?report=members|services.
- Members: selling (native + USD), cost (sum of actual fulfillment cost_amount, USD-normalized),
  gross profit, margin, paid, due — with totals.
- Services: revenue / cost / profit / margin per service_type.
- Selling converted to USD with the booking exchange rate (1 USD = X native, divide — same rule
  as add_umrah_transaction.php) so profit is comparable to USD cost_amount.
- E2E-verified: F1 hotel cost 2100 -> profit 1740, margin 45.3%; F2 visa cost 150 -> profit 3690.

PHASE 27 — Build Supplier Payables

Now the supplier side can become financial.

Example:

Supplier ABC

Flight        $4,500
Visa          $850
Hotel       SAR 25,000
Transport     $900

Total Payable

This should come from actual fulfilled services rather than manually typed totals.

DONE — admin/umrah_finance.php (Payables tab) + report=suppliers.
- Aggregates umrah_fulfillments (status <> 'cancelled', cost_amount not null) per supplier,
  pivoted by fulfillment_type: flight / hotel / visa / transport / meal / ziyarat + Total Payable.
- Purely derived from fulfilled services — no manual totals.
- E2E-verified: supplier 98 hotel 2100 / total 2100, supplier 100 visa 150 / total 150.

PHASE 28 — Build reporting

Reports should be derived from the transactional data.

Member profitability
Member
Selling
Cost
Profit
Margin
Service profitability
Service
Revenue
Cost
Profit
Supplier report
Supplier
Services
Cost
Payable
Hotel report
Hotel
Rooms
Reservations
Occupancy
Contract utilization
Outstanding payments
Member
Total
Paid
Due

DONE — admin/umrah_finance.php (Reports tab) + report=hotels|outstanding.
- Hotel report: rooms, reservations (stays), occupied today + occupancy % (occupancy_helper),
  inventory rooms + contract utilization %, active contracts.
- Outstanding payments: active/pending members with due > 0, total / paid / due (native + USD).
- Member + service profitability live on the Finance tab.
- E2E-verified: hotel rooms=2 reservations=1 occupancy 50% utilization 100%; outstanding lists F1.
PHASE 29 — Add security and permissions

Then control who can do what.

Example:

Admin
Umrah Manager
Sales
Operations
Hotel Manager
Finance
Viewer

For example:

Sales
→ create member
→ select package
→ record payment

Operations
→ assign supplier
→ fulfill services

Hotel Manager
→ hotels
→ rooms
→ contracts
→ inventory

Finance
→ payments
→ costs
→ profit
→ supplier payable

DONE — granular capability-based permissions layered on the existing role auth.
- admin/includes/umrah_permissions.php: capability map per role (view, member_create,
  member_edit, payment_record, fulfill, hotel_manage, finance_view, reports_view),
  helpers umrah_can() / umrah_require($cap, 'json'|'page') / umrah_roles_with();
  auto-loaded from admin/security.php so every admin page + API has it.
- New roles: operations, hotel_manager, viewer — added to $admin_roles, employee
  create/edit forms (add_employee.php, edit_employee.php, users.php), dashboard
  allowed roles, navbar adminRoleGroup; i18n labels in en/fa/ps.
- Gate map:
  Sales         -> member_create, member_edit, payment_record, view
  Operations    -> fulfill (assign supplier, fulfill services), view
  Hotel Manager -> hotel_manage (hotels, rooms, contracts, inventory), view
  Finance       -> payment_record, finance_view, reports_view, view
  Umrah Manager -> everything; Admin -> everything + manage_users
  Staff/Viewer  -> view (read-only)
- Endpoints gated: add_umrah.php / add_umrah_multi.php / create_family.php
  (member_create), delete_booking.php / approve_umrah_booking.php (member_edit),
  add_umrah_transaction.php / add_family_umrah_transactions.php /
  delete_umrah_transaction.php (payment_record), save_fulfillment.php (fulfill),
  save_hotel.php / save_contract.php (hotel_manage), get_finance_report.php
  (finance_view). Read-only dashboards (member/hotel/calendar) stay open to all
  staff roles.
- admin/umrah_hotels.php: page = view; Add buttons + row actions + contract
  actions hidden when !umrah_can('hotel_manage') (window.canManageHotels).
- admin/umrah_finance.php: finance_view page gate.
- Nav (nav_items.php): hotel_management and finance_management items only shown
  to roles holding the capability.
- E2E-verified: 64-case role × endpoint matrix (8 roles × 8 endpoints) all green;
  denied => HTTP 403 "Access denied", allowed => gate passes; zero DB leftovers.
PHASE 30 — Add audit logs

For important changes:

Who?
When?
What?
Before?
After?

For example:

08 Aug 2026

User: Ahmed

Changed Hotel Supplier

Old:
Supplier A

New:
Supplier B

This is extremely important once the system controls money and operational commitments.

DONE — audit logging for every important Umrah change (shared activity_log table,
viewable in admin/activity_log.php).
- admin/includes/umrah_audit.php: central umrah_audit($pdo, action, table, recordId,
  old, new) helper writing activity_log (user_id, created_at, action, table_name,
  record_id, old_values, new_values, ip_address, user_agent, tenant_id, branch_id);
  auto-loaded from admin/security.php. Runs inside the same DB transaction as the
  change, so audit is atomic with the operation.
- save_fulfillment.php: fulfillment create => action 'add'; update => 'update' with
  REAL Before/After (old vs new supplier_id, status, currency, cost, dates) —
  fixes the legacy '{}' old-values. Covers the plan's "Changed Hotel Supplier"
  example exactly.
- save_hotel.php: hotels, room types and rooms all audit add/update/toggle/delete
  with old vs new values (toggle logs status active <-> inactive).
- save_contract.php: contracts audit add/update/toggle/delete; new_values include
  inventory_count and rate_count.
- Legacy APIs (add_umrah, add_umrah_transaction, delete_*, approve, refunds, ...)
  already wrote activity_log and are untouched.
- E2E-verified (umrah_audit_driver): 34 checks — who (user_id), when (created_at),
  what (action/table/record), before/after JSON old vs new for hotel create/rename/
  toggle/delete, room type, rooms, contract create/toggle/delete, fulfillment
  create + supplier change 98->99 with status change; permission-denied call writes
  NO audit row; cleanup leaves zero leftovers in business tables + activity_log.
PHASE 31 — Add validation and database protection ✅ DONE

Every important operation should have validation.

- ✅ Booking (add_umrah.php / add_umrah_multi.php): member name required; family_id + package_id required; package must exist and be active (`id=? AND tenant_id=? AND status='active'`); paid/bank/discount >= 0; bank receipt number required when `received_bank_payment > 0`; due cannot be negative (overpayment capped as no-negative); all rejects return 400 + `success:false`.
- ✅ Payment (add_umrah_transaction.php): `payment_amount > 0`; currency ∈ {USD, AFS}; booking must exist for tenant/branch (404 `Booking not found for this tenant/branch.`) before any write; bank/internal-account receipt rules retained.
- ✅ Hotel fulfillment (save_fulfillment.php): supplier_currency ∈ {USD, AFS}, supplier_cost >= 0, exchange_rate > 0; hotel/room-type/room must be tenant-owned and active; room-type must match room; check-in/out required, check-out > check-in, nights = ceil(DATEDIFF/86400); nightly_rate >= 0; hotel requires supplier currency; explicit contract_id must be active, own hotel, stay inside valid_from..valid_to, and room covered by active contract inventory; if a room is bound to active contract inventory (`ci.status='active' AND c.status='active'`) its stay must be covered by the contract window; double-booking on same room+dates rejected (self excluded via `status NOT IN ('cancelled','checked_out','not_assigned')`); contract_id persisted to umrah_hotel_fulfillments; all rejects emit 400 before response.
- ✅ Supplier: cannot assign inactive supplier/hotel/room (checked at fulfillment).
- ✅ Price: invalid currency/negative cost/zero exchange rate rejected.

Verification: Phase 31 E2E driver 40/40 PASS (+ 8 booking rejects, 3 payment rejects, 16 fulfillment rejects, 5 persisted fulfillments with nights + audit rows, zero DB leftovers); perms regression 64/64, audit regression ALL GREEN, finance + hotels regressions PASSED.

PHASE 32 — Use database transactions ✅ DONE

Operations that create multiple records should be atomic: BEGIN → Create Booking → Create Sold Services → Price Snapshots → COMMIT; on failure ROLLBACK — no half-created bookings.

- ✅ All multi-record Umrah writers already wrap BEGIN/COMMIT/ROLLBACK: booking (add_umrah.php, add_umrah_multi.php), payments (add_umrah_transaction.php, add_umrah_refund_transactoin.php, update_umrah_payment.php, update_umrah_refund.php, refund_umrah_transaction.php, update_refund_umrah_transaction.php, process_umrah_refund.php, delete_umrah_transaction.php, delete_umrah_refund.php, delete_umrah_refund_transactions.php), fulfillment (save_fulfillment.php), approvals/date changes (approve_umrah_booking.php, approve_date_change_request.php, process_date_change_request.php, delete_date_change_request.php), cancellation/reapply (process_cancellation_reapply.php, process_bulk_cancellation_reapply.php), deletes (delete_booking.php, delete_family.php), group tickets (save_group_ticket.php), family money (add_family_umrah_transactions.php), member updates (update_umrah_member.php).
- ✅ Consistency: DB failure paths in add_umrah.php, add_umrah_multi.php, add_umrah_transaction.php now reject with 500 + `message` (was 200/`error`).
- ✅ Atomicity E2E driver (27/27): forced REAL mid-transaction DB errors (strict-mode column overflow) through the APIs — booking+service insert (booking persisted then service exploded → booking, services and audit all rolled back), multi-member batch (member-1 booking+service rolled back when member-2 failed), payment on hotel-only booking (500, zero transaction rows, paid/balances untouched), fulfillment (fulfillment + hotel details + sold-service status + audit all rolled back when the status-history insert failed) — plus positive controls proving the commit path still persists everything.
- Regressions after Phase 32 edits: Phase 31 40/40, perms 64/64, audit ALL GREEN, finance + hotels drivers PASSED.

PHASE 33 — Testing ✅ DONE

Layer-test suite `umrah_phase33_driver.php` (41 checks, all green, zero leftovers) exercising every layer through the real APIs:

- ✅ Package tests: create package + service lines + rates + supplier costs readable via `get_package` (totals math verified); edit (rename + new rate wins — latest active rate scored); add service line; remove service line (is_active=0); deactivate → unresolvable + booking rejected 400; change price → **historical price frozen (Rule 8)** — booking keeps sold price + price snapshots after later price changes.
- ✅ Booking tests: member+booking created from package selection, sold-service rows generated, totals/discount/profit/due exact; payment recorded → due/paid recomputed; AFS sale currency stored with exchange rate and USD payment converted (multiply rule).
- ✅ Supplier tests: assign at fulfillment, change supplier (same fulfillment), per-service override (two services, two independent suppliers); "apply to all" awaits the Phase 34 bulk-action UI.
- ✅ Hotel tests: reserve room, prevent double booking (same room+dates), release room (check-out) then re-assign.
- ✅ Financial tests: selling price/profit exact, AFS supplier cost → USD cost_amount conversion, bank payment reflected in supplier balance.

Bug found and fixed by this phase: sale currency/exchange rate was never persisted on bookings with NULL branch (`branch_id = ?` never matched) — add_umrah.php + add_umrah_multi.php now use the NULL-safe branch pattern; AFS bookings previously stayed USD (payments were converted with the wrong rule).

Regressions after fix: Phase 31 40/40, Phase 32 27/27, perms 64/64, audit ALL GREEN, finance + hotels PASSED.
PHASE 34 — Only now upgrade admin/umrah.php ✅ DONE

At this point, admin/umrah.php becomes primarily a presentation/operational interface, not the place where all business logic lives.

The flow becomes:

admin/umrah.php
       ↓
Booking/Member layer
       ↓
Business services
       ↓
Database

Rather than:

admin/umrah.php
   ↓
500 different SQL queries
   ↓
price calculations
   ↓
supplier logic
   ↓
hotel logic
   ↓
payment logic
   ↓
everything

This is how we avoid the giant monolithic page the original discussion warns against.

Delivered in this phase:
- ✅ Audit of admin/umrah.php (1,624 lines) + every admin/umrah*.php: ZERO inline DML on umrah tables — all writes already delegate to api/umrah/*.php via bundled JS fetch calls (approve_booking.js → approve_umrah_booking.php, bookings.js → deleteFamily, ...). The page is already a presentation/operational layer in substance; no monolithic refactor was required.
- ✅ NEW `api/umrah/apply_supplier_to_services.php`: bulk ("apply to all") supplier assignment on a booking's sold services — the only write kind that had no endpoint. enforce_auth + umrah_require('fulfill') + CSRF; booking must exist for tenant/branch (404); supplier must be active + tenant-owned (400); optional `service_type` and `service_ids` filters; single scoped UPDATE + one umrah_audit row per changed service, all inside one transaction (batch atomicity, Phase 32 style); JSON `{success, updated, message}`; 405/400/403/404/500 codes.
- ✅ Fix in `api/umrah/get_suppliers.php`: NULL-safe branch pattern (`branch_id = ? OR (branch_id IS NULL AND ? IS NULL)`) for suppliers + main account — same NULL-branch bug class found in Phase 33; NULL-branch tenants previously got an empty supplier dropdown.
- ✅ UI: "Apply to All" button in admin/umrah_detail.php "Supplier Services" card (visible only with the `fulfill` capability); Swal select fed by get_suppliers.php, POSTs booking_id + supplier_id, toast + reload.
- ✅ E2E driver (21/21 green, zero leftovers): apply-to-all 3 services updated + 3 audit rows with real before(98)/after(new) JSON; no-op same supplier → updated=0 + no audit rows; service_type filter; service_ids filter; empty-scope ids → updated=0; foreign-tenant supplier 400, inactive supplier 400, missing booking 404, missing inputs 400, bad CSRF 403; all rejected calls leave data untouched; NULL-branch scoping exercised throughout.
- Regressions after Phase 34 edits: Phase 31 40/40, Phase 32 27/27, Phase 33 41/41, perms 64/64, audit ALL GREEN, finance + hotels PASSED, purge zero leftovers, lint clean.

PHASE 35 — Final system structure ✅ DONE

Delivered in this phase — structural audit (`umrah_phase35_structure_audit.php`, 39/39 green) proving the running system matches the diagram:
- ✅ Every diagram box maps to a real table: FAMILY `families`; MEMBER = inline attributes on `umrah_bookings` (name/fname/gfname/relation/dob/gender/passport_number — no separate member table, per the legacy structure) with `family_id` → `families`; BOOKING `umrah_bookings` (package_id → `umrah_packages`); PACKAGE `umrah_packages` + `umrah_package_services` + service master (`umrah_service_categories`/`umrah_services`) + price versions (`umrah_service_rates`); PAYMENTS `umrah_transactions` (umrah_booking_id) + mirror `client_transactions` (transaction_of); SOLD SERVICES `umrah_booking_services` (booking_id/service_id/service_rate_id + price_snapshot); FULFILLMENT `umrah_fulfillments` (booking_service_id → sold service, supplier_id → `suppliers`) + hotel path `umrah_hotel_fulfillments` (fulfillment_id/hotel_id/contract_id/room_id); CONTRACT `umrah_hotel_contracts` + INVENTORY `umrah_hotel_contract_inventory`; ROOMS/TYPES `umrah_hotel_rooms`/`umrah_hotel_room_types`; COST/PROFIT columns on bookings, sold services (base_price/profit) and fulfillments (supplier_cost/cost_amount conversion).
- ✅ Edges verified column-by-column (35 connector checks).
- ✅ Family-line purity: `family_id` exists ONLY on umrah_bookings among all umrah tables — nothing else attaches directly to Family.
- ✅ Data integrity: zero orphan rows across all 16 referential edges; every sold service carries a price snapshot; every service resolves to a category.
- ✅ Process audit: `delete_booking.php` cascades umrah_transactions → client/supplier/main-account transactions → services → booking (tenant/branch scoped), so today's write paths cannot produce orphans. One PRE-EXISTING legacy orphan found (`umrah_transactions.id=150`, tenant 9, booking deleted outside the API) — purged after confirmation; zero orphans remain.
- Post-verification fix: dashboard.php (and any page including `includes/header.php` without `admin/security.php`) hit `Call to undefined function umrah_can()` in `includes/nav_items.php` — the nav gating used the Phase 29 capability helper without loading it. `nav_items.php` now self-boots `umrah_permissions.php` on demand (guard: `if (!function_exists('umrah_can'))`). Smoke-verified via include-context harness: admin sees hotel/finance menus; staff sees neither (view-only), nav renders 21.9KB clean.
- Full regressions untouched: Phases 31/32/33/34 drivers all green (40/27/41/21), perms 64/64, audit/finance/hotels PASSED.

PHASE 36 — Package Management UI ✅ DONE

Delivered in this phase — packages previously had NO admin UI (DB-seeded only, plan steps 4–9); now fully manageable:
- ✅ `api/umrah/packages/save_package.php`: create/edit/toggle/delete (entity=package) + add/update/delete lines (entity=line). CSRF + `umrah_require('package_manage')`; validation 400s (name/code required, duplicate code tenant-scoped, line service must exist+active, quantity > 0); delete blocked with 400 when `umrah_bookings.package_id` references it ("deactivate it instead"); DB failures 500; one transaction + one `umrah_audit` row per write.
- ✅ `api/umrah/packages/get_packages.php`: tenant/branch-scoped package list + per-line price-engine preview (latest active rate / supplier cost via local resolveRate/resolveSupplierCost: hotel_id=4, room_type_id=2, meal_plan_id=1, season priority +0.5, latest-id tiebreak; supplier cost overrides rate cost_price) + `dictionaries` (services/categories/hotels/room_types) for the line editor.
- ✅ `admin/umrah_packages.php` page: card grid (price-engine totals), package modal (name/code/status/description), line editor with category optgroups, hotel/room-type pickers, live line totals, toggle/delete with Swal; buttons gated by `package_manage`. `js/umrah/packages.js` registered in `js/umrah/bundle_files.php`, page-only via `#btnAddPackage` guard; SweetAlert2 CDN + `.uh-badge` pills match the hotel page style.
- ✅ `package_manage` capability added to admin + umrah roles; nav entry + `navTrigger` updated in nav_items.php.
- ✅ Translations: all 19 new package keys (`add_package` … `services_in_package`, `no_packages`, `select_service`) appended to `includes/languages/{en,fa,ps}/common.php` — previously `__()` returned the raw key ("services_in_package" rendered bare on the card subtitle); verified all resolve through the Language class in all three locales; php -l clean.
- ✅ Post-launch UI fixes: (1) service-line dropdown was blank when the modal was opened before `get_packages` resolved (race) — `addLineRow()` now renders a translated "Select service…" placeholder first (`buildSvcOptions`/`buildHotelOptions`/`buildRtOptions` refactor) and `refreshLineRows()` repopulates any open rows once data arrives, preserving selections; (2) left a stale `groupHtml` reference in the row template after the refactor (ReferenceError on Add Line) — fixed to use `svcHtml`; (3) `js/umrah/bundle.php` + `css/bundle.php` now send `Cache-Control: no-store` + `Expires: 0` so a stale cached bundle can never mask a code fix again; (4) leftover packages from an aborted test run were purged and the E2E cleanup hardened to own all test artifacts by name prefix (`P36-%`/`XP36-%`), verified across two consecutive runs (29/29 both).
- ✅ E2E driver (29/29 green, zero leftovers): P1–P6 package CRUD gates (dup code 400, missing name/code 400, bad/missing CSRF 403), L1–L5 lines with totals (350+100×14 → 1750/1540, then 1050 after qty 14→7), V1–V6 line validation walls (missing package, inactive service, zero qty, round-trip, idempotent re-save), T1–T2 status toggle reflected in get_packages, D1–D2 delete-blocked-while-booked (guard booking created under a real family row) then deleted with audit row.
- ✅ Permission matrix extended: save_package/get_packages gates verified across all 8 roles (80/80 total) — admin+umrah allowed, all others denied.
- Regressions after Phase 36 edits: Phase 31 40/40, 32 27/27, 33 41/41, 34 21/21, 35 structure audit 39/39 (restored after purging 3 orphan services left by an aborted test run — driver cleanup hardened to own its seeds by code), perms 80/80, audit/finance/hotels PASSED, purge clean, lint clean.

PHASE 37 — Service Master Manager ✅ DONE

Delivered in this phase — services previously had NO admin UI (the old "umrah_services" page only VIEWS booked services; master rows were DB-seeded only). Per user review the design is FLAT: categories are NOT part of the flow (Service = name + code + pricing unit + status + prices). Service manager at `admin/umrah_services_manager.php` (nav item gated by `umrah_can('service_manage')`).
- ✅ `api/umrah/services/save_service.php`: service create/update/toggle/delete + rate create/update/toggle/delete. CSRF + `umrah_require('service_manage')`; category entity removed (flat); `category_id` optional → NULL. Walls: name required, duplicate code tenant-scoped, rate needs existing service, active+tenant hotel/room-type (room type must match hotel), date range, currencies USD/AFS, non-negative prices, exchange_rate > 0. Deletes blocked with 400 when referenced by a package line ("used by a package line") or sold bookings ("sold bookings"); rate deletions blocked when `umrah_booking_services.service_rate_id` references them; service delete cascades its rates + supplier costs. One transaction + one `umrah_audit` row per write.
- ✅ `api/umrah/services/save_supplier_cost.php`: entity=supplier_cost (save/toggle/delete); active+tenant supplier required, same walls as rate, status active|inactive.
- ✅ `api/umrah/services/get_services.php`: flat contract — `services` (with rate_count/cost_count + latest ACTIVE rate selling/cost via id-desc subselects), `rates` (joined hotel/room names), `supplier_costs` (joined supplier), dicts `hotels`/`room_types`/`suppliers`, `pricing_units` (8), `rate_statuses` (3). No `categories` key.
- ✅ `admin/umrah_services_manager.php` page: services table (name/code, pricing-unit pill, latest sell/base, rate+cost count badges), service modal with Rates editor (hotel/room-type pickers, sell/cost USD, status active|draft|pending) + Supplier Costs editor (supplier, unit cost, status). Buttons + all writes gated by `service_manage`. `js/umrah/services_manager.js` registered in bundle (page-only via `#btnAddService` guard); labels via `window.svcLabels`.
- ✅ Nav: Services entry under the Umrah management submenu (`service_master` key), `navTrigger` extended; `service_manage` capability added to admin + umrah roles.
- ✅ Translations: 24 new keys appended to `includes/languages/{en,fa,ps}/common.php` (service_master, add_service, unit_cost, supplier_costs, status_updated, no_rates/no_costs/no_services, confirm_delete_svc …). Placement bug fixed (script appended after the array's closing `];` — repositioned into the array; php -l + key-resolution verified in all three locales).
- ✅ Packages dropdown integration: `get_packages` returns `category_name: null` for flat services; `buildSvcOptions` groups them under the existing `all_services` label instead of an empty optgroup.
- ✅ E2E driver (54/54 green, zero leftovers): S1–S6 service CRUD (audit rows, category_id NULL, dup code 400, missing name 400, unit defaulting), R1–R10 rate walls (negative 400, EUR 400, inverted dates 400, bogus hotel/room-type 400, missing service 400, draft excluded then toggled active → latest_selling 350→400), C1–C7 supplier-cost walls (bogus supplier 400, negative 400, EUR 400, toggle), P1–P4 package integration (line wiring, service in get_packages dict with null category, totals use latest active rate 400 / base 300), D1–D5 delete protections (package-line block, sold-bookings block via guard booking + `umrah_booking_services` row, cascade delete of rates+costs, audit rows), G1 flat contract. Cleanup owns seeds by `SVC37%`/`P37-%` prefixes.
- ✅ Permission matrix extended: save_service/save_supplier_cost/get_services gates across 8 roles → 104/104 total (admin+umrah allowed, all others denied).
- Regressions: Phase 31 40/40, 32 27/27, 33 41/41, 34 21/21, 36 29/29, perms 104/104, audit/finance/hotels/fulfillment/dashboard PASSED, purge clean, php -l + node --check clean, live bundle verified (services_manager.js present, no category code).

When finished, the system should conceptually be:

                         FAMILY
                            │
                 ┌──────────┼──────────┐
                 │          │          │
              MEMBER     MEMBER     MEMBER
                 │
              BOOKING
                 │
        ┌────────┼─────────┐
        │        │         │
     PACKAGE  PAYMENTS  SOLD SERVICES
                           │
            ┌──────────────┼───────────────┐
            │              │               │
          FLIGHT          VISA           HOTEL
            │              │               │
       FULFILLMENT    FULFILLMENT     FULFILLMENT
            │              │               │
         SUPPLIER       SUPPLIER       SUPPLIER
                                            │
                                  ┌─────────┴─────────┐
                                  │                   │
                               CONTRACT           ON-DEMAND
                                  │                   │
                               ROOM/INVENTORY       HOTEL
                                  │
                                COST
                                  │
                               PROFIT

And remember the Family line is intentionally simple:

Family
   └── Members

Nothing else belongs directly to Family.

The actual development order I recommend

If we are going to build this together, I would follow this exact sequence:

Step	Build	Result
1	Inspect current system	Understand existing code/database
2	Freeze business rules	No architectural ambiguity
3	Database architecture	Final relationships
4	Service master	Flight/Visa/Hotel/etc.
5	Packages	Standard/Premium/VIP
6	Package services	What each package contains
7	Package configuration	Nights, options, requirements
8	Selling-price engine	Customer pricing
9	Price versions	Historical prices protected
10	Member/Booking	Connect package to member
11	Sold services	Record what was sold
12	Payments	Member-level financials
13	Suppliers	Generic supplier system
14	Supplier capabilities	Which services each supplier provides
15	Supplier rates	Procurement costs
16	Hotels	Hotel master
17	Room types/rooms	Physical inventory
18	Contracts	Contracted hotel inventory
19	Contract rates	Hotel procurement rates
20	On-demand	External hotel procurement
21	Fulfillment	Actually provide services
22	Supplier assignment	Connect fulfillment to suppliers
23	Cost snapshots	Freeze actual costs
24	Statuses	Operational tracking
25	Member dashboard	Central member view
26	Hotel dashboard	Hotel operations
27	Hotel calendar	Inventory visibility
28	Finance	Cost/profit/balance
29	Supplier payables	Supplier accounting
30	Reports	Management information
31	Permissions	Secure operations
32	Audit logs	Accountability
33	Validation/transactions	Data integrity
34	Testing	Verify everything
35	Upgrade admin/umrah.php	Professional final UI
36	Production deployment	Live system
One important decision

We should not jump directly to Step 34 or start randomly editing admin/umrah.php.

The next real technical step should be Step 1: inspect your existing MTravels code/database, then Step 2: produce the final database architecture based on what already exists.

After that, we can construct the system incrementally and safely.