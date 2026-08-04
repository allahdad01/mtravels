# Payments Journal — Implementation Plan

> **Goal:** Give agencies a single point from which they can view, record, and manage **all** payments across every module of the system (tickets, visas, umrah, hotels, additional payments, JV client–supplier, salaries, expenses, sarafi, fund/withdraw/transfer), in one unified chronological register.

---

## 1. How payments work today (findings)

The system is a multi-tenant travel-agency platform. Each agency (tenant) has **three account classes** and **three ledgers**:

| Account | Table | Balances | Ledger |
|---|---|---|---|
| Main accounts (internal cash / bank) | `main_account` | USD, AFS, EUR, DARHAM | `main_account_transactions` (running `balance` per account+currency) |
| Clients (incl. `client_type='agency'`) | `clients` | usd_balance, afs_balance | `client_transactions` |
| Suppliers | `suppliers` | single balance + currency | `supplier_transactions` |

### Money flows — where each payment is recorded

All handlers live under `api/` with the admin UI in `admin/`. Every handler is a self-contained DB transaction: update running balance → insert ledger row → `notifications` + `activity_log` (+ optional WhatsApp). Booking-level `paid` / `due` / `received_bank_payment` fields are updated separately in the booking tables.

| Flow | Direction | `transaction_of` | Handler |
|---|---|---|---|
| Ticket sale | Credit main acct | `ticket_sale` | `api/ticket/add_ticket_payment.php` |
| Ticket reserve / date change / weight | Credit main acct | `ticket_reserve` / `date_change` / `weight` | `api/ticket_reserve/…`, `api/ticket_date_change/…`, `api/ticket_weight/…` |
| Visa sale | Credit main acct | `visa_sale` | `api/visa/add_visa_transaction.php` |
| Hotel booking | Credit main acct | `hotel` | `api/hotel/add_hotel_transaction.php` |
| Umrah (Bank / Internal Acct) | Credit main acct (or external supplier) | `umrah_transaction` | `api/umrah/add_umrah_transaction.php` |
| Refunds (ticket/visa/hotel/umrah) | Debit main acct | `*_refund` | `api/ticket_refund/…`, `api/visa/…`, `api/hotel/…`, `api/umrah/…` |
| Additional payments (visa/iqama/invitation…) | Credit main acct | `additional_payment` | `api/additional_payment/add_additional_payment_transaction.php` |
| **JV (client↔supplier)** | Client → Supplier, **main account NOT touched** | only `jv_payments` + `jv_transactions` + `client_transactions` + `supplier_transactions` | `admin/process_client_supplier_jv.php` |
| Fund main / supplier / client | Credit/Debit main acct | `fund` / `supplier_fund` / `client_fund` | `api/accounts/fund_*.php` |
| Withdraw supplier / client, transfer | Main acct debit/credit | `withdraw_fund` / `transfer` | `api/accounts/withdraw_*.php`, `transfer_balance.php` |
| Salary payments & advances | Debit main acct | `salary_payment` | `admin/salary_payment.php`, `salary_advances.php` |
| Expenses | Debit main acct | `expense` | `api/expense/expense_actions.php` |
| Debtors / Creditors | Main acct debit/credit | `debtor` / `creditor` | `api/debtor/…`, `api/creditor/…` |
| Sarafi deposit/withdrawal/hawala | Main acct credit/debit | `*_sarafi` | `admin/sarafi.php` |
| Budget allocation | Main acct debit | `budget_allocation` | `api/allocation/allocation_actions.php` |

### Existing "single points"

- `admin/accounts.php` — hub for the 3 account classes + per-account history modals
- `admin/finance_tracker.php` — finance-role only, per-currency metric cards
- Per-module detail tabs (e.g. `ticket_detail.php`, `visa_detail.php`, `umrah_detail.php`, `payment_detail.php`)
- `admin/fetch_statement.php` + `print_statement.php` — per-entity statements

---

## 2. Gaps (why agencies want a journal)

1. **No unified chronological register** — a payment is only visible inside its booking detail tab or its account history modal. You cannot see *all* money in/out for a day across modules.
2. **JV payments are invisible to main-account reports** — they never touch `main_account_transactions`; only client/supplier ledgers (`admin/process_client_supplier_jv.php:203-225`).
3. **No cross-currency totals** — 4 currencies + per-transaction `exchange_rate`; no base-currency normalization anywhere.
4. **No single-point recording** — to post money you must navigate to the right module/modal.
5. **No unified filtering/export** (date/type/party/account/receipt) and no drill-through from one screen.
6. **No receivables view** — expected vs received exists per booking only.

---

## 3. Recommended approach — 3 phases

### Phase 1 (MVP, low risk, no schema change): Unified read-only journal + action hub

- **New page `admin/payment_journal.php`** (roles: admin, finance — same gate as `admin/accounts.php:18-22`).
- **New API `api/journal/get_journal_entries.php`** — one normalized dataset via `UNION ALL` of:
  - `main_account_transactions` (all 28 `transaction_of` types)
  - `client_transactions` (credit/debit per client)
  - `supplier_transactions`
  - `jv_payments`

  Normalized columns: `entry_date`, `type` (credit/debit), `amount`, `currency`, `base_amount` (converted via `exchange_rate` against a default rate), `account` (main/supplier/client), `party_name`, `module` (`transaction_of`), `reference_id`, `receipt`, `description`, `branch_id`.

- **UI** (reuse the design language of `admin/jv_payments.php`):
  - Sticky KPI strip: today's inflow/outflow, month totals, per-currency totals, net cash position.
  - Filters: date range, module type, currency, account, party search, receipt, branch.
  - Paginated table with drill-down links to existing detail pages (`ticket_detail.php?id=…`, `visa_detail.php?…`, `umrah_detail.php?…`, `payment_detail.php?…`) — `reference_id` + `transaction_of` map 1:1.
  - **Action Hub**: "Record Payment ▾" quick-launch panel deep-linking into existing modals/handlers (Ticket/Visa/Umrah/Hotel payment, Additional Payment, Fund Client, Fund Supplier, JV, Expense, Salary) — a single point of entry without duplicating business logic.
  - CSV/PDF export of the filtered register.
  - i18n keys in `includes/languages/{en,fa,ps}/common.php`.
- **New nav item** in the sidebar include under `admin/includes/`.

### Phase 2 (target architecture): Write-through journal table + central service

- **New table `payment_journal`**:
  `id, journal_no, tenant_id, branch_id, entry_date, module (transaction_of), party_type, party_id, account_type, account_id, debit, credit, currency, base_amount, exchange_rate, ref_table, ref_id, receipt, description, created_by, created_at, status(active/void)`
- **New service `includes/classes/PaymentJournal.php`** with `record(Entry $e)` — single write path that also maintains per-currency running balances. Phase 2 handlers call the service instead of hand-rolling ledger inserts (they keep their booking/notification logic).
- **Backfill script `scripts/backfill_payment_journal.php`** — idempotently migrates existing `main_account_transactions`, `client_transactions`, `supplier_transactions`, `jv_payments` rows into the journal.
- Journal page switches to the single table → instant filters, correct cross-currency totals, and enables future features: void/reversal with audit trail, approval workflow, reconciliation, daily closing.

### Phase 3 (optional extensions)

- **Expected payments view**: join booking `due`/`sold_price` to show outstanding receivables per client in the journal.
- **Client-portal view** (`client/`): agencies (clients) see a read-only journal of *their own* payments.
- Print-friendly daily cashbook / journal voucher receipt.

---

## 4. Key implementation details

- **Security**: follow existing conventions — `security.php` `enforce_auth(['admin','finance'])`, CSRF via `verify_csrf_token()`, `DbSecurity::validateInput()`, tenant+branch scoping on every query (as in `admin/jv_payments.php:37-44`).
- **Currency normalization**: reuse the rule already in `api/umrah/add_umrah_transaction.php:384-406` (AFS base → multiply, USD base → divide) with a default rate from the `exchange_rates` table when `exchange_rate` is null.
- **Files to create (Phase 1)**: `admin/payment_journal.php`, `api/journal/get_journal_entries.php`, `api/journal/export_journal.php`, `js/journal/journal.js`, `modals/journal/record_payment_modal.php`, lang keys, nav item.
- **Files to touch (Phase 1)**: sidebar nav include, language files (en/fa/ps).
- **Files to create (Phase 2)**: `database_migration_payment_journal.sql`, `includes/classes/PaymentJournal.php`, `scripts/backfill_payment_journal.php`.

---

## 5. Risks & notes

- **Naming**: "journal" already maps to JV (Journal Voucher) in the UI — use "Payments Journal" labeling and keep JV as a module type inside it.
- **Drift**: `main_account_transactions.balance` is maintained manually on edit/delete (e.g. `admin/update_salary_payment.php:120-157`); Phase 1 must compute running balances from the UNION rather than trusting per-table balances.
- **JV gap**: include `jv_payments` explicitly (Phase 1) — it is the only money movement outside the main-account ledger.
- **No behavior change** in Phase 1: read-only + deep links, so booking flows stay untouched; Phase 2 migrations must be tested against the `backups/` dumps.

---

## 6. Effort estimate

| Phase | Effort | Deliverables |
|---|---|---|
| Phase 1 | 3–5 days | 1 page + 2 APIs + JS + modal + i18n + nav |
| Phase 2 | 4–6 days | Schema + service + migration of ~15 handlers + backfill script |
| Phase 3 | 2–3 days | Receivables view + client portal view + cashbook printing |
