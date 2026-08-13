# MTravels Umrah — Phase 1: Current System Map

Status: COMPLETE
Date: 2026-08-08
Source plan: `umrah_plan.md`

---

## 1. Tech stack & conventions

- Plain PHP (no framework), PDO with `PDO::ERRMODE_EXCEPTION`, emulated prepares off,
  STRICT_TRANS_TABLES (`includes/db.php`).
- Multi-tenant, multi-branch: every business table carries `tenant_id` + `branch_id`
  (branch is `bigint(20) DEFAULT NULL`, sometimes nullable in seeds).
- InnoDB, `utf8mb4_unicode_ci`, `created_at`/`updated_at` timestamps on every table.
- Auth: `admin/security.php` -> `enforce_auth()`; roles `admin|finance|sales|umrah`
  (plus super-admin/tenant-super-admin tiers outside admin/).
- CSRF: `verify_csrf_token()` + `window.csrfToken` in every form/API.
- Input validation: `DbSecurity::validateInput()` in `admin/includes/db_security.php`.
- i18n: `__('key')` via `includes/language_helpers.php` (en / ps / fa / dari).
- UI: Bootstrap 4 + SweetAlert2 + Select2; page JS served by `js/umrah/bundle.php`
  (file list in `js/umrah/bundle_files.php`, ~30 modules); modals under `modals/umrah/`.
- Server-side action handlers in `api/umrah/*`.php, called via fetch/AJAX.
- Write-path logging: `activity_log` (user_id, action, table_name, record_id,
  old/new VALUES as JSON, ip, user_agent, tenant, branch). Also `audit_logs` table exists.
- Financial side-effects: `client_transactions`, `supplier_transactions`,
  `main_account_transactions` all carry `transaction_of = 'umrah'` (+ umrah_refund,
  umrah_date_change, umrah_cancellation...) and `reference_id` pointing at the booking.

## 2. Current Umrah data model

```
families
   │  (head_of_family, contact, province/district, package_type [free text],
   │   visa_status enum(Applied,Issued,Not Applied), tazmin,
   │   total_price/total_paid/total_paid_to_bank/total_due  <- DENORMALIZED AGGREGATES)
   │
   └── umrah_bookings          (the member IS the booking)
          ├── sold_to -> clients.id       (client wallet holder)
          ├── paid_to  -> users.id
          ├── member fields: name/fname/gfname, relation, dob, gender,
          │    passport_number, passport_expiry, id_type, flight_date,
          │    return_date, duration, room_type, photo/passport/visa paths + upload dates
          ├── FINANCIAL: price (base), sold_price, discount, profit,
          │    received_bank_payment, bank_receipt_number, paid, due,
          │    currency (USD/AFS), exchange_rate
          ├── status: active | refunded | cancelled | pending
          │
          ├── umrah_booking_services   <-- SOLD SERVICES already exist
          │     service_type (free text), supplier_id FK, base_price,
          │     sold_price, profit, currency
          │     + snapshot columns (service_id, service_rate_id, pricing_unit,
          │       quantity, supplier_currency/amount, exchange_rate, cost_currency/amount,
          │       selling_currency/amount, total_cost, total_selling, price_snapshot)
          │
          ├── umrah_transactions       <-- member payments (type 'Credit' only)
          │     payment_date, description, amount, receipt, currency, exchange_rate
          │
          ├── umrah_refunds            <-- full/partial per booking
          │     refund_amount, supplier_penalty, service_penalty, base, sold
          │
          ├── group_tickets            <-- flight grouping (member_ids + family_ids JSON,
          │     airline, PNR, legs *2, return legs *2, status active)
          │
          └── date_change_umrah / date_change_tickets
```

### Financial state today

- Money already lives on the member/booking, NOT the family — consistent with plan Rules 1 and 11 (payments belong to the member, never to the family).
- `umrah_transactions` = member payments; `group_tickets` = a flight grouping.
- **The families table still carries denormalized `total_price/total_paid/total_due` and
  `visa_status`.** The card view derives payment percentage from these columns. This is
  the only place the "Family owns money" smell remains (plan wants family = grouping only).

### admin/umrah.php views

- Family view: one card per family + expandable members (AJAX `load_family_members.php`).
- Members view (filter=members): table of bookings w/ full row action dropdown
  (approve, transaction, tazmin/agreement/completion/cancellation PDFs, ID card,
  group ticket, date change, docs upload, refund, delete).
- Flights view (filter=flights): group_tickets with member/family grouping, totals per
  currency, rooming/manifest exports (print + Excel, Dari/Pashto/English), client report.
- Filters: visa_status pills (Not Applied/Applied/Issued), refunded, cancelled.
- Pagination 12/page, search across members/passports/family/client.

## 3. Existing workflows (actions + APIs)

- Add Family: `modals/umrah/create_family_modal.php` -> `api/umrah/create_family.php`
- Add Member: `modals/umrah/umrah_modal.php` (single) / `umrah_modal_multi.php` (multi-member)
  -> `js/umrah/add_member_multi_refactored.js` + `bookings_multi.js`
  -> `api/umrah/add_umrah.php` / `add_umrah_multi.php` / `add_family_umrah_transactions.php`.
  The form builds a **manual services grid**: staff picks `service_type` (free text),
  `supplier_id` (from `api/umrah/get_suppliers.php`), currency, `base_price`, `sold_price`.
  `price` = SUM(base), `sold_price` = SUM(sold) - discount, `profit` = sold - base,
  `due` computed; 6-month passport validation; photo/passport OCR upload.
  Insert DB transaction (booking + services + currency update + activity_log) with rollback.
- Payments: `modals/umrah/transaction_modal.php` + `js/umrah/transaction_manager.js`
  -> `api/umrah/add_umrah_transaction.php` etc.; writes umrah_transactions +
  ledger side-entries.
- Refunds: `modals/umrah/refund_modal.php` + `js/umrah/refund.js`
  -> `api/umrah/process_umrah_refund.php` (full/partial, penalties).
- Date changes, cancellations (approval flow -> approve_umrah_booking),
  approvals (`status pending -> active`), ID cards, agreement/completion/cancellation
  PDF generations (template files), group tickets, multi-ticket invoice,
  member details modal, documents modal (photo/passport/visa).
- Member detail: `admin/umrah_detail.php` (1,636 lines) - booking + services +
  client/supplier/main-account ledger streams.

## 4. Key finding: the price engine EXISTS in SQL but is UNWIRED

`database_migration_umrah_price_engine.sql` (idempotent) creates:

- `umrah_services` (catalog: code, pricing_unit),
- `umrah_service_categories`,
- `umrah_hotels`, `umrah_hotel_room_types` (occupancy/bed), `umrah_hotel_meal_plans`,
- `umrah_seasons` (named date ranges w/ priority),
- `umrah_service_rates` (the engine core:
  supplier_currency/supplier_price/exchange_rate/cost_currency/cost_price/
  selling_currency/selling_price/markup flat%/percent %, valid_from/to, status draft/pending/active),
- `umrah_packages` + `umrah_package_services` (no stored price),
- adds snapshot columns to `umrah_booking_services` (guarded by information_schema checks).

`database_seed_umrah_price_engine.sql` inserts demo data (tenant 1): 5 categories,
6 services (HTL-MAK, HTL-MAD, TRN-APT, TRN-MAK-MAD, VISA-UMR, ZIY-MAK),
2 hotels (Makkah Hilton, Madinah Crown), room types, meal plans, 3 seasons,
rates with SAR->USD conversion, "Standard Umrah" package + lines.

**BUT: no PHP page references `umrah_service_rates`/`umrah_packages`/`umrah_services`.
`admin/umrah_services.php` is actually the booked-services admin (umrah_booking_services
listing) — not a catalog UI. The membership flow therefore works WITHOUT the price engine:
staff types services manually.**

## 5. Other relevant tables / modules discovered

- `suppliers` — generic master (Internal/External, category, currency, balance,
  route_payment_to_main_account, active/inactive). No capability vector, no service rates.
- `client_transactions`, `supplier_transactions` (+ `_records` for quarterly reports),
  `main_account`, `main_account_transactions`, `general_ledger` — financial side effects.
- `visa_applications` + `visa_documents`/`visa_refunds` — separate module
  (admin/visa.php, api/visa/*). Bookings only carry `families.visa_status`.
- `hotel_bookings` + `hotel_refunds` — **separate hotel module** (admin/hotel.php).
  NOT the planned hotel subsystem (which uses umrah_hotels etc.).
- `group_tickets` (grouped flight), `date_change_umrah`.
- Front desk "packages" are free text `families.package_type`
  (values: Visa, Hotel, Services, Visa+Services, etc.).
- `users` roles: admin/finance/sales/umrah (also staff/HR/tenant levels).
- Security: rate limiting, login attempts, password resets, 2FA (TOTP),
  ip_blacklist, encryption keys, security_audit_log.

## 6. Reuse vs extend

| Area | Verdict | Notes |
|---|---|---|
| families | REUSE | Keep table + card view. Plan: strip aggregates/`visa_status` mirror later (Family = grouping only). |
| umrah_bookings | REUSE | Member == booking already; financial columns already here. |
| umrah_booking_services | REUSE + EXTEND | The sold-service backbone + snapshots exist. Add `status`, fulfillment refs, dates, fulfillment config. |
| umrah_transactions / umrah_refunds | REUSE | Member-level payments/refunds correct per plan. |
| suppliers | REUSE (base) + EXTEND | Need capability matrix + service-rate tables (Phases 12–13). |
| Price engine tables (migration) | REUSE | Already Phase 3–8 DB. Need UI + wiring into booking flow. |
| Hotel subsystem (rooms/contracts/inventory/rates/on-demand) | NEW (not built anywhere) | Do not confuse with existing `hotel_bookings` module. |
| Fulfillment + statuses | NEW | No fulfillment tables; services have no status field. |
| Permissions granularity | NEW | Roles exist; module-level perms don't (Phase). |
| Audit trail | REUSE | activity_log already does who/what/before/after per operation. |
| DB transactions | REUSE | add_umrah* already use beginTransaction/commit/rollback. |

## 7. What this means for the remaining plan

- Phases 3–8 (service master, packages, package services, accommodation config,
  selling-price engine, price versions/snapshots) are effectively DONE in schema+seed;
  the real work is: (a) admin/umrah_services.php becomes the catalog/rates UI, and
  (b) shipping the booking flow to package selection instead of the manual grid.
- Phases 12–21 (supplier matrix, hotel rooms/contracts/inventory/rates/on-demand,
  fulfillment, cost snapshots) require new tables — these are the Phase 2 design task.
- Phases 22 (statuses), 29 (permissions), 30 (audit — mostly exists), 31–33 exist
  partially.
- `admin/umrah.php` is already a PIM-like presentation layer in UI (page 1,616 lines);
  plan Phase 33/34 = slim the page into a pure booking layer once the service layer grows.

## 8. Suggested next actions

1. Produce Phase 2 gap schema (new tables only: supplier_matrix, hotel_contracts,
   contract_items, contract rates/inventory, fulfillment, status engine).
2. Wire catalog to booking: booking modal loads `umrah_packages` + rate engine,
   auto-generates sold services; `add_umrah*.php` writes snapshots + transactional.
3. Build catalog UI (umrah_services.php rewrite: categories/services/hotels/rooms/
   meals/seasons/rates/packages CRUD).
4. Anything else only after 1–3 are closed (hotel ops, finance, reports…).