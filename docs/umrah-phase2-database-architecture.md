# MTravels Umrah — Phase 2: Final Database Architecture

Status: DESIGN (pending approval)
Date: 2026-08-08
Depends on: Phase 1 map (`docs/umrah-phase1-current-system-map.md`)

---

## 1. What already exists (REUSE, do not recreate)

| Assets | Notes |
|---|---|
| `umrah_service_categories`, `umrah_services`, `umrah_hotels`, `umrah_hotel_room_types`, `umrah_hotel_meal_plans`, `umrah_seasons`, `umrah_service_rates`, `umrah_packages`, `umrah_package_services` | From `database_migration_umrah_price_engine.sql`. Phases 3–8 of the plan are already at DB level. |
| `umrah_booking_services` + price-snapshot columns | Sold services + historical price protection (plan Rule 8). |
| `families`, `umrah_bookings`, `umrah_transactions`, `umrah_refunds`, `group_tickets`, `date_change_umrah` | Core booking/family/member/payment model. |
| `suppliers` | Generic supplier master. |
| `clients`, `users`, `activity_log`, `audit_logs`, `exchange_rates` | Auth, audit, FX master. |
| `hotel_bookings`/`hotel_refunds` | **Separate module — out of scope.** |

## 2. What is missing (the Phase 2 gap)

The plan requires these blocks; nothing exists yet:

1. **Supplier capability matrix** (which supplier can provide which service).
2. **Supplier cost rates** (what MTravels pays, per service — *separate from selling*).
3. **Physical hotel rooms** (permanent inventory).
4. **Hotel contracts** (which rooms/period are under contract, at what procurement rate).
5. **Contract inventory** (room-per-period validity).
6. **On-demand hotel purchase** (no contract rows; direct cost capture).
7. **Fulfillment** (how MTravels delivers each sold service) with typed details
   (hotel / flight) and generic detail rows (visa, transport, meal, ziyarat, insurance).
8. **Service status engine** (controlled status sets + status history).
9. Small column additions on existing tables (`umrah_booking_services.status`,
   `umrah_package_services.is_required`).

## 3. Final architecture (umrah workspaces only)

```
FAMILY
  └── umrah_bookings  (member)                          [existing]
        ├── umrah_booking_services   [existing + status]
        │     └── umrah_fulfillments                 NEW
        │           ├── umrah_fulfillment_details    NEW (generic key/value)
        │           ├── umrah_hotel_fulfillments     NEW (typed hotel)
        │           └── umrah_flight_fulfillments    NEW (typed flight)
        │     └── umrah_booking_service_statuses     NEW (history)
        ├── umrah_transactions       [existing]
        └── umrah_refunds            [existing]

CONFIGURATION (existing, from price-engine migration):
  umrah_service_categories -> umrah_services
  umrah_packages -> umrah_package_services (+hotel/room/meal/season refs)
  umrah_service_rates  (SELLING-side: cost/selling/markup, dated)
  umrah_seasons

SUPPLIER (new):
  suppliers (existing)
    ├── umrah_supplier_services      capabilities per supplier
    ├── umrah_supplier_service_rates procurement cost matrix (dated, by service/hotel)
    └── umrah_supplier_transactions  (existing ledger, reuse)

HOTEL INVENTORY (new):
  umrah_hotels, umrah_hotel_room_types (existing)
    ├── umrah_hotel_rooms            physical rooms
    ├── umrah_hotel_contracts        contracts over rooms/floors/entire hotel
    ├── umrah_hotel_contract_inventory  rooms in contract + dates
    └── umrah_hotel_contract_rates   procurement rate per room type (per night)
```

## 4. New tables — definition summary

### 4.1 `umrah_supplier_services` — capability matrix
- supplier_id FK `suppliers`, service_id FK `umrah_services`, `is_capable` default 1,
  `is_active`, notes. Unique (supplier_id, service_id, tenant_id).
- Purpose: Phase 12 — "ABC Travel does Flights+Visa; XYZ only Hotels".

### 4.2 `umrah_supplier_costs` — supplier procurement rates (deliberately named "costs",
not "rates", to avoid confusion with the selling engine)
- supplier_id, service_id, hotel_id? (hotel scope), room_type_id?, meal_plan_id?,
  season_id?, valid_from/to, pricing_unit, currency (e.g. SAR), unit cost,
  cost currency, exchange_rate, status active/inactive, notes.
- Purpose: Phase 13 — supplier cost per service; nothing to do with selling.

### 4.3 `umrah_hotel_rooms` — permanent physical rooms
- hotel_id FK, room_type_id FK, room_number, floor, status
  (active/inactive/maintenance), notes.
- Unique (hotel_id, room_type_id, room_number) — no duplicates (replaces the
  "hotel_rooms" concept from the plan).

### 4.4 `umrah_hotel_contracts`
- hotel_id FK, contract_number (e.g. CT-2026-004), supplier_id nullable,
  scope enum('entire_hotel','floor','specific_rooms'), valid_from, valid_to,
  payment_terms text, notes, status enum('active','inactive','expired').

### 4.5 `umrah_hotel_contract_inventory`
- contract_id FK, room_id FK `umrah_hotel_rooms`, valid_from, valid_to
  (default = contract validity when NULL), UNIQUE (contract_id, room_id).
- Purpose: which rooms MTravels can actually use under the contract in a date range.

### 4.6 `umrah_hotel_contract_rates`
- contract_id FK, room_type_id FK, meal_plan? NULL, period (valid_from/to),
  nightly rate (currency), cost currency + conversion rate, status.
- Purpose: Phase 17 — procurement rate, NOT customer price.

### 4.7 `umrah_fulfillments` — how a sold service gets delivered
- booking_service_id FK `umrah_booking_services`, fulfillment_type
  enum('hotel','flight','visa','transport','meal','ziyarat'), supplier_id (nullable),
  status varchar(30) — driven by event charts (see §5),
  requested_date, planned_date, completed_date (nullable),
  supplier_currency, supplier_cost, exchange_rate, cost_currency, cost_amount,
  notes, created_by.
- Cost columns = the **Phase 21 snapshot** (frozen when confirmed; rate changes
  later do not rewrite history).
- Each sold service may have 0..n fulfillments (e.g. a sold "Meal" plan may be
  fulfilled per meal, but normally 1).

### 4.8 `umrah_fulfillment_details` — generic key/value store
- fulfillment_id FK, detail_key, detail_value (for visa/transport/meal/ziyarat
  specifics). Keeps the schema flexible without creating a table per service type.

### 4.9 `umrah_hotel_fulfillments` — typed hotel fulfillment
- fulfillment_id FK, hotel_id FK, contract_id?, inventory_id?, room_id?, room_type_id,
  check_in DATE, check_out DATE, nights INT, nightly_rate, currency, cost_amount.
- Supports contract OR on_demand (when inventory unassigned; supplier_id on header).

### 4.10 `umrah_flight_fulfillments` — typed flight
- fulfillment_id FK, ticket_number, pnr, airline, flight_number, departure,
  arrival, departure_time, arrival_time, return_flight_number, return_departure/arrival,
  class, cost fields (header holds cost snapshot).

### 4.11 `umrah_service_statuses` — controlled status catalog
- status_group enum('hotel','visa','flight','generic'), code, label, is_active, sort_order.
- Seeds from plan Phase 22.

### 4.12 `umrah_booking_service_statuses` — status history
- booking_service_id FK, old_status, new_status, changed_by FK, changed_at, notes.
- Every change is recorded (Phase 30 audit asset).

### 4.13 ALTERs on existing tables
- `umrah_booking_services`: ADD `status VARCHAR(30) NULL DEFAULT 'pending'`
  (after `price_snapshot` — existing rows keep working); 
  ADD `is_optional TINYINT(1) NOT NULL DEFAULT 0`.
- `umrah_package_services`: ADD `is_required TINYINT(1) NOT NULL DEFAULT 1`.
  Optional services are expressed as `is_required = 0` — no separate column.
- No FK on `umrah_booking_services.service_id` (legacy data may not match); foreign
  keys stay soft (indexed columns) except where the DB already enforces them.

## 5. Status rules (Phase 22 design)

| Domain | Statuses (enum) |
|---|---|
| generic | pending, assigned, requested, confirmed, completed, cancelled |
| hotel | not_assigned, reserved, confirmed, checked_in, checked_out, cancelled |
| flight | not_ticketed, reserved, ticketed, changed, cancelled |
| visa | not_applied, applied, processing, issued, rejected, cancelled |
| transport | pending, assigned, confirmed, completed, cancelled |
| meal/ziyarat | pending, confirmed, completed, cancelled |

The string is stored on `umrah_fulfillments.status` and mirrored onto
`umrah_booking_services.status` for display; history lives in the log table.

## 6. Orders & invariants (Phase 31–32 hooks)

- Booking + sold services + snapshots: created in ONE transaction (already true in
  add_umrah.php).
- Fulfillment creation must set supplier + cost snapshot atomically (begin/commit).
- Room reservation: only if room exists in contract inventory AND status=available
  for the date range — checked at the API layer (calendar helper later).
- Historical snapshots immutable: `umrah_fulfillments.supplier_cost`, cost_currency,
  exchange_rate are set at confirm time and never updated in place.
- No new hardcoding of service types: they reference `umrah_services` via
  `umrah_booking_services.service_id` (legacy rows keep `service_type` string).

## 7. Migration artifact

Applies via `database_migration_umrah_gap_tables.sql` (idempotent):
- CREATE TABLE IF NOT EXISTS for all §4 tables.
- info_schema-guarded ALTERs for the two existing tables.
- Seed inserts for `umrah_service_statuses` (guard `WHERE NOT EXISTS`).

Run once in phpMyAdmin/mysql. Safe to re-run.