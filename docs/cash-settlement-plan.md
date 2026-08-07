# Finance → Admin Cash Settlement ("Handover") — Implementation Plan

> **Goal:** Let the finance user automatically see how much money they have collected but not yet handed over to admin, submit that balance to admin, and have admin confirm it — at which point the finance counter resets to zero for ongoing tracking. All history is preserved and reviewable later. No manual entry of collected cash.

---

## 1. Background & findings

The finance user currently tracks collected cash **manually** in a standalone `finance_tracker` table (`admin/finance_tracker.php`), typing income/expense by hand per branch. That counter is:

- disconnected from the real accounting ledgers,
- hard-limited to `usd`/`afs` (enum),
- error-prone and time-consuming.

The real money flow already lives in the ledgers. Every customer payment, refund, expense, salary, and supplier payment is recorded as a `credit` (money in) or `debit` (money out) in **`main_account_transactions`**, per currency (USD, AFS, EUR, DARHAM, SAR).

### Key gap (confirmed against the live schema)
`main_account_transactions` has **no `created_by` column**. To know *which user* handled which money (and who is responsible), attribution must be added and populated at every write site.

- **~50 `INSERT INTO main_account_transactions` sites across ~35 files.**
- Every handler currently runs inside a logged-in session, so `$_SESSION['user_id']` is available at each write.
- Automated/background writes that have no session user should leave `created_by` as `NULL` (excluded from per-user counters).

---

## 2. Decision summary (locked with the user)

| Decision | Choice |
|---|---|
| **Counter source** | Auto — derived from `main_account_transactions`, attributed (credit/debit) to the finance user who created the transaction. |
| **Multi-currency** | Per-currency rows; extend to all 5 system currencies (USD, AFS, EUR, DARHAM, SAR, uppercase). |
| **Settlement amount** | Partial allowed — finance can submit any amount up to what is currently available. |
| **Admin confirm effect** | **Tracking only** — NO `main_account_transactions` write on confirm. The money already moved to main accounts at transaction time. Confirming merely marks how much has been handed over. |
| **Counter over time** | "Since last settlement" — the counter reflects money since the user's last *confirmed* settlement; confirming resets the starting point forward. |
| **History** | All settlements retained; viewable later by user/currency/date/status. |

---

## 3. Money-flow model (auto counter)

For the logged-in **finance user**, per currency:

```
remaining =
    SUM(main_account_transactions WHERE created_by = <finance user>
                        AND created_at > (last CONFIRMED settlement datetime)
                        AND currency = <currency>
                        AND type = 'credit')        -- money IN
  - SUM(main_account_transactions WHERE ... same scope ...
                        AND type = 'debit')         -- money OUT
```

Key behavior:

- `credit` = cash the user received → increases what they owe/need to hand over.
- `debit` = cash the user paid out → decreases it.
- **"Since last settlement"**: once admin *confirms* a settlement, that settlement becomes the new marker; the counter restarts forward from there.
- Per-currency amounts + `exchange_rate` convention (`toUsdBase()` divide-by-rate) available for cross-currency totals if needed.

---

## 4. Database changes

### 4.1 `main_account_transactions`
```sql
ALTER TABLE `main_account_transactions`
  ADD `created_by` INT NULL AFTER `branch_id`,
  ADD KEY `idx_mat_created_by` (`created_by`, `created_at`);
```
Nullable so existing/retro rows remain valid (historical attribution is not reliably recoverable).

### 4.2 New table `cash_settlements`
```sql
CREATE TABLE `cash_settlements` (
  `id`            INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `tenant_id`     INT NOT NULL,
  `branch_id`     INT NOT NULL,
  `user_id`       INT NOT NULL,                    -- finance user being settled
  `currency`      ENUM('USD','AFS','EUR','DARHAM','SAR') NOT NULL DEFAULT 'USD',
  `amount`        DECIMAL(14,2) NOT NULL,
  `status`        ENUM('pending','confirmed','rejected') NOT NULL DEFAULT 'pending',
  `request_note`  TEXT NULL,
  `requested_by`  INT NULL,
  `created_at`    TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `confirmed_by`  INT NULL,
  `confirmed_at`  DATETIME NULL,
  `rejected_by`   INT NULL,
  `rejected_at`   DATETIME NULL,
  `reject_reason` VARCHAR(500) NULL,
  PRIMARY KEY (`id`),
  KEY `idx_cs_branch_user` (`branch_id`, `user_id`, `currency`, `status`),
  KEY `idx_cs_tenant` (`tenant_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```
Both schema changes mirrored into `database_structure.sql` (and a `database_migration_cash_settlement.sql`).

### 2.3 (tracker) Multi-currency support
- `finance_tracker.currency` → widen to `ENUM('USD','AFS','EUR','DARHAM','SAR')` (uppercase) so the manual tracker can optionally still store all currencies.
- Update `api/finance/finance_tracker_actions.php` and `admin/finance_tracker.php` to use all currencies generically (validation + `get_balances` loops) instead of hard-coded `usd`/`afs` keys.

---

## 5. Attribution — the main body of work

Populate `created_by = $_SESSION['user_id']` at **every** `INSERT INTO main_account_transactions` site (file-by-file):

| Area | Files |
|---|---|
| Tickets | `add_ticket_payment.php`, `add_refund_ticket_payment.php`, `save_weight_transaction.php`, `add_ticket_reserve_payment.php`, `add_date_change_ticket_payment.php` |
| Visa | `add_visa_transaction.php`, `process_visa_refund_transaction.php` |
| Umrah | `add_umrah_transaction.php`, `refund_umrah_transaction.php`, `add_umrah_refund_transactoin.php`, `add_family_umrah_transactions.php` |
| Hotel | `add_hotel_transaction.php`, `add_hotel_refund_transaction.php`, `refund_hotel_transaction.php` |
| Additional payments | `add_additional_payment_transaction.php` |
| Accounts | `fund_main_account.php`, `fundClient.php`, `fund_supplier.php`, `withdraw_fund.php`, `withdraw_main_fund.php`, `withdraw_client.php`, `transfer_balance.php` |
| Expense / Debtor / Creditor | `expense_actions.php`, `debtors_handler.php`, `creditor_handler.php` |
| Allocations | `allocation_actions.php`, `global_allocation_actions.php` (admin) |
| Sarafi / Salary | `sarafi.php` (admin), `salary_payment.php`, `salary_advances.php` (admin) |

Mechanically: add `created_by` to the column list and bind `$_SESSION['user_id']`. Verify each handler has a valid session user at that point (they do — they are user-facing POST handlers).

---

## 6. API — `api/finance/cash_settlements.php`

Roles `admin` + `finance`, CSRF via `verify_csrf_token()` on POST, `activity_log` on every mutation, scoped by `tenant_id` + `branch_id`.

Actions:

- **`summary`** → per-currency auto counter for a finance user
  `{ currency, credit_in, debit_out, remaining, handed (confirmed), pending }`.
- **`create`** (finance) → validate `amount ≤ remaining` for that currency; insert `pending` row; notify admin.
- **`confirm`** (admin) → set `status='confirmed'`, record `confirmed_by` / `confirmed_at`; notify finance. This becomes the new "last settlement" marker.
- **`reject`** (admin) → set `status='rejected'` + `reject_reason`, record `rejected_by` / `rejected_at`; notify finance.
- **`list`** → paginated history for a user or all users, filter by `status`/`currency`/date range.

Notifications via `notifications` table with `recipient_role` = `admin` / `finance`.

---

## 6. Page — `admin/cash_settlement.php`

One page, two role-aware views (role from `$_SESSION['role']`), same design language as `finance_tracker.php`.

- **Finance view**
  - Per-currency metric cards: Remaining (auto), Handed Over, Pending.
  - "Submit to Admin" button per currency → modal (amount + note).
  - "My submissions" history table with status badges.
- **Admin view**
  - Pending settlements list → Confirm / Reject (reject requires a reason).
  - Full history table (grouped by finance user / currency / date / status).

---

## 7. Navigation + i18n

- `includes/nav_items.php`: add **"Cash Settlement"** menu item in the Finance & Accounting section, gated to roles `finance` and `admin`.
- Add translation keys to `includes/languages/{en,fa,ps}/common.php` for all new labels.

---

## 8. Edge cases & notes

- **Historical rows**: `created_by = NULL` — excluded from per-user counters (cannot be attributed retroactively).
- **Automatic/background transactions**: keep `created_by = NULL`.
- **Partial settlements**: allowed; validation enforces `amount ≤ remaining`.
- **Cross-currency**: per-currency by default; base-USD conversion (if ever needed) reuses the `toUsdBase()` divide-by-rate convention.
- **Counter reset**: after confirm, next `summary` recomputes from the confirmed datetime forward.
- **No ledger mutation on confirm**: settlement is a tracking/approval record only.

---

## 9. Verification

- `php -l` on all touched PHP files.
- Apply DB migration; confirm columns + new table via schema check.
- CLI test: seed controlled credit/debit rows for a finance user → assert `summary.remaining` math (credit − debit, since last confirmed settlement).
- Browser/render check: both role views; submit → admin sees pending → confirm → finance counter resets at that point.
- Confirm `activity_log` + `notifications` are written for create/confirm/reject.

---

## 10. Suggested rollout (reduce risk)

There are two ways to land this broad change:

- **Option A — Pilot first (recommended):** add the column, attribute only 2–3 primary handlers (e.g. `ticket_sale`, `visa_sale`, `add_additional_payment_transaction`), build the settlement API + page, and validate the whole flow end-to-end before touching all ~35 files.
- **Option B — Full in one pass:** implement ALL ~50 insert-site attributions plus everything above in one systematic pass.

> Note: option A lets you confirm the UX/math is right before the large mechanical migration, reducing risk. Once validated, Option B is just the remaining mechanical edits.