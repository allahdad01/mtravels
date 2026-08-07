# Plan: Add Saudi Riyal (SAR) Currency to the System

## Goal

Add **Saudi Riyal (SAR)** as a 5th supported currency, duplicating the existing **DARHAM/AED** implementation and renaming it to SAR. The balance column will be `sar_balance`.

## Current State (Study Findings)

### Currencies today
| Code (internal) | Display | Balance column |
|---|---|---|
| USD | USD | `usd_balance` |
| AFS | AFS | `afs_balance` |
| EUR / EURO | EUR | `euro_balance` |
| DARHAM / AED | AED | `darham_balance` |
| SAR | SAR | `sar_balance` (new) |

Note: the codebase is inconsistent — it uses both `DARHAM` and `AED` (and both `EUR` and `EURO`) interchangeably. Currency handling is duplicated across ~40 files with hardcoded lists and if/else chains; there is **no central currency config**.

### Database (`database_structure.sql`)
- `main_account` balance columns: `usd_balance`, `afs_balance`, `euro_balance`, `darham_balance` → **`sar_balance` added** (lines 1574–1577)
- Currency ENUMs:
  - `main_account_transactions.currency` = `enum('USD','AFS','EUR','DARHAM','SAR')` (line 1596) ✓
  - `global_budget_allocations.currency` = `enum('USD','AFS','EUR','DARHAM','SAR')` (line 1300) ✓
- `suppliers` / `clients` currency = `enum('USD','AFS')` (suppliers line 2295) — unchanged unless SAR supplier/client accounts are required later
- `exchange_rates`, `exchange_transactions`, `customer_wallets` use `varchar(10)` — no schema change needed
- `creditors`, `debtors`, `assets`, `budget_allocations`, `expenses` etc. use `varchar` — no schema change needed

## Decisions

- Internal code: **SAR** (ISO 4217), display label "SAR"
- Balance column: **`sar_balance`** (decimal(10,3), mirrors `darham_balance`)

## Implementation Status

### ✅ Completed — Step 1: Database migration
- `database_migration_sar_currency.sql` created:
  ```sql
  ALTER TABLE main_account
    ADD COLUMN sar_balance decimal(10,3) NOT NULL DEFAULT 0.000;

  ALTER TABLE main_account_transactions
    MODIFY COLUMN currency enum('USD','AFS','EUR','DARHAM','SAR') NOT NULL;

  ALTER TABLE global_budget_allocations
    MODIFY COLUMN currency enum('USD','AFS','EUR','DARHAM','SAR') NOT NULL;
  ```
- `database_structure.sql` mirrored to match.

### ✅ Completed — Step 2: Core currency → balance mapping
All currency→balance maps updated with `'SAR' => 'sar_balance'` / `case 'SAR'` / `elseif ($x === 'SAR')` branches:

- `api/accounts/`: `withdraw_fund.php`, `fund_supplier.php`, `update_transaction.php` (3 branches: +/−/main), `delete_main_account_transaction.php`, `delete_supplier_transaction.php`, `fund_main_account.php` (SELECT + full SAR branch incl. transaction/activity logging), `get_main_account.php`
- `api/visa/`: `update_visa_transaction.php`, `update_visa_payment.php`, `delete_visa_transaction.php`, `delete_visa_refund_transaction.php`, `process_visa_refund_transaction.php`, `delete_visa.php` (credit+debit chains), `add_visa_transaction.php` (ternary)
- `api/ticket/`: `delete_ticket.php`, `add_ticket_payment.php`, `delete_ticket_payment.php`, `update_ticket_payment.php`
- `api/ticket_date_change/`: `delete_ticket_dc.php`, `add_date_change_ticket_payment.php`, `delete_date_change_ticket_transaction.php`, `update_date_change_transaction.php`
- `api/ticket_refund/`: `delete_ticket_rf.php`, `delete_refund_ticket_transaction.php`, `update_refund_transaction.php`
- `api/ticket_reserve/`: `delete_ticket_reserve.php`, `add_ticket_reserve_payment.php`, `delete_ticket_reserve_payment.php`, `update_ticket_reserve_payment.php`
- `api/ticket_weight/`: `delete_weight.php`, `delete_weight_transaction.php`, `update_weight_transaction.php`, `save_weight_transaction.php`
- `api/hotel/`: `delete_hotel_booking.php`, `add_hotel_transaction.php`, `add_hotel_refund_transaction.php`, `delete_hotel_refund.php`, `delete_hotel_refund_transactions.php`, `delete_hotel_transaction.php`, `update_hotel_transaction.php`, `update_refund_hotel_transaction.php`
- `api/umrah/`: `delete_booking.php`, `delete_family.php`, `delete_umrah_refund.php`, `delete_umrah_refund_transactions.php`, `delete_umrah_transaction.php` (2 spots), `add_umrah_refund_transactoin.php`, `update_refund_umrah_transaction.php`, `update_umrah_payment.php`
- `api/creditor/`: `update_creditor_transaction.php` (+ all handler spots verified safe)
- `api/debtor/`: `update_debtor_transaction.php` (+ all handler spots verified safe)
- `api/expense/`: `expense_actions.php` (2 spots)
- `api/allocation/allocation_actions.php` (3 if/else blocks + SQL CASE + 1-line map)
- `api/additional_payment/`: `add_additional_payment_transaction.php`, `delete_additional_payment_transaction.php`
- `admin/`: `update_salary_payment.php`, `sarafi.php` (3 ternaries + currency_config + stat arrays), `delete_sarafi_deposit/withdrawal/hawala.php`, `update_sarafi_deposit/withdrawal/hawala_transaction.php`, `excel_import_handler.php` (INSERT column list), `global_allocation_actions.php` (5 spots)
- `api/journal/entity_picker.php` (SELECT list)

**Validator whitelists:** `'SAR'` added in `admin/includes/db_security.php:331` and `tenant_super_admin/db_security.php:359`.

**No change needed (already SAR-safe):** `api/creditor/creditor_handler.php`, `api/debtor/debtors_handler.php`, `api/debtor/delete_debtor_transaction.php` — they use the `strtolower($currency) . '_balance'` default, so SAR resolves to `sar_balance` automatically.

### ✅ Completed — Step 3: UI dropdowns and display
- `admin/accounts.php` (SAR balance card + dropdown + `.sar` CSS color)
- `admin/creditors.php` (3 dropdowns), `admin/debtors.php` (2 dropdowns)
- `admin/dashboard.php` (financeChartCurrency)
- `admin/sarafi_modals.php` (6 dropdowns + 2 dividePairs JS arrays + sampleRates)
- `admin/global_budget_allocation.php` (dropdown)
- `admin/payment_journal.php` (filter foreach + 10 options arrays + `.sar` badge CSS)
- `admin/assets.php` (2 dropdowns + 2 validator spots)
- `admin/customers.php`, `admin/customer_detail.php` (currency colors + validator list + 2 dividePairs)
- **36 modal files** under `modals/` — `<option value="SAR">` inserted after each DARHAM option (BOMs stripped after batch edit)
- `tenant_super_admin/main_accounts.php` — SUM query, Total SAR stat card, table balance row, modal detail cell, JS population, CSS colors

### ✅ Completed — Step 4: Exchange rates
- `admin/includes/exchange_handler.php` dividePairs + SAR pairs
- `admin/customer_detail.php` (2 dividePairs arrays)
- `admin/additional_payments.php` — `$usdToSar = 3.75` fallback + 3 conversion branches
- `admin/date_change.php`, `admin/hotel.php` — **not changed**: they use `exchange_rate` generically (currency-agnostic)
- `exchange_rates` / `getCurrentMarketRate` already handle any varchar-based currency pair — no change needed

### ✅ Completed — Step 5: Translations
- `includes/languages/{en,fa,ps}/common.php`: `'sar'` existed; `'sar_balance'` keys added to all 3; `'sar'` added to en second currency block

### ✅ Completed — Step 6: Reports
- `api/expense/export_comprehensive_report.php` + `tenant_super_admin/export_comprehensive_report.php`: 9 SQL `CASE WHEN currency = 'SAR'` sums each (all 9 sources incl. refunds block), SAR accumulation in row processing, currency arrays, `['USD','AFS','EUR','DARHAM','SAR']` foreach
- `api/journal/get_journal_entries.php` + `export_journal.php` (`allowed_currencies`)
- `api/dashboard/get_dues_summary.php` + `get_debtors.php`: SAR in rate maps, `in_array` lists, AFS/USD conversion branches in `convertAmountToBase`
- `api/dashboard/get_financial_data.php`: `SUM(sar_balance)` branch + client-credits skip branch
- `api/report/export_statement.php`: balance-field ternary
- `admin/finance_tracker.php` — **not changed** (USD/AFS only, no DARHAM — out of scope)

### ✅ Completed — Step 7: Verification
- **Migration applied to live DB** (`mtravels`, MariaDB 10.4.32): `sar_balance decimal(10,3) NOT NULL DEFAULT 0.000` added to `main_account`; both currency enums now include `SAR` (verified via `information_schema`)
- **DB smoke tests pass (7/7, rolled back — no data persisted):**
  - SAR fund main account → `sar_balance` increments correctly
  - SAR withdraw → decrements correctly
  - Delete-flow credit reversal → decrements correctly
  - Dashboard `SUM(sar_balance)` query runs
  - Transaction `SUM(amount)` for `currency = 'SAR'`
  - `global_budget_allocations` accepts SAR (enum insert)
  - `darham_balance` untouched by SAR ops
- **Lint:** all modified files pass `php -l` (478 files checked across `api/`, `admin/`, `modals/`, `includes/languages/`)
- **App boots:** `http://localhost/mtravels/` returns HTTP 200
- **Remaining (manual, UI only):** log in and visually confirm — SAR options in currency dropdowns, SAR card on `admin/accounts.php` and `tenant_super_admin/main_accounts.php`, sarafi deposit/withdraw/hawala flows, expense report export with SAR rows

## Out of Scope (unless requested)
- Adding SAR to `suppliers`/`clients` currency ENUM (`enum('USD','AFS')`)
- Marketing copy on `about.php:951`, `pricing.php` (mentions AFN/AED)
- Super-admin plan/subscription currency lists (varchar-based, no change required)
- `admin/finance_tracker.php` (USD/AFS only)
