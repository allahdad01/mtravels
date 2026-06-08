# Sarafi Module — Complete Feature Documentation

## Overview

The Sarafi (Currency Exchange / Money Changer) module handles customer wallet management, deposits, withdrawals, Hawala (informal money transfers), and currency exchange operations with full multi-currency support, main account integration, and audit trail.

---

## 1. Dashboard & Overview

**Page:** `admin/sarafi.php`

### Summary Stats Bar
- Total wallet balances per currency: USD, AFS, EUR, AED (plus any additional currencies)
- Color-coded cards: USD (teal), AFS (emerald), EUR (indigo), AED (amber)

### Quick Action Bar — 5 action buttons
| Action | Modal | Keyboard Shortcut |
|--------|-------|-------------------|
| New Deposit | `#depositModal` | `D` key |
| New Withdrawal | `#withdrawalModal` | `W` key |
| Hawala Transfer | `#hawalaModal` | `H` key |
| Currency Exchange | `#exchangeModal` | `E` key |
| New Customer | `#customerModal` | — |

### Transaction Table
- Paginated (10 per page), columns: Date, Customer (avatar), Type (icon), Amount (+/-), Currency, Reference, Status, Actions
- Row colors: green (deposit), rose (withdrawal)
- Client-side search/filter
- Transaction count badge

### Transaction Detail Modal
- Customer info (name, phone, wallet balance)
- Transaction info (type, amount, reference, status, date, notes)
- Hawala-specific: commission, secret_code, receiver info
- Receipt image display + print button

### Edit Transaction Modal
- Edit amount, reference, notes for deposit/withdrawal transactions

### Toast Notifications
- Success/error toasts, auto-dismiss after 4.5s

---

## 2. Deposits

**Modal:** `#depositModal` | **Reverse:** `admin/delete_sarafi_deposit.php`

| Step | Detail |
|------|--------|
| Select | Customer + Main Account |
| Enter | Amount, Currency (USD/EUR/AFS/DARHAM), Reference (auto `DEP` prefix), Notes |
| Upload | Optional receipt (image/PDF, max 10MB) |
| System | Creates `sarafi_transactions` (type `deposit`), credits `customer_wallets`, credits `main_account`, records `main_account_transactions` (credit, `deposit_sarafi`), creates notification |
| Reverse | Reverses wallet, main account, and subsequent balance tracking |

---

## 3. Withdrawals

**Modal:** `#withdrawalModal` | **Reverse:** `admin/delete_sarafi_withdrawal.php`

| Step | Detail |
|------|--------|
| Select | Customer + Main Account |
| Enter | Amount, Currency, Reference (auto `WDR` prefix), Notes |
| Upload | Optional receipt |
| Validation | Customer wallet + main account sufficient balance |
| System | Debits `customer_wallets`, debits `main_account`, records `main_account_transactions` (debit, `withdrawal_sarafi`), creates notification |
| Reverse | Reverses wallet, main account, subsequent balances |

---

## 4. Hawala Transfers (Informal Money Transfer)

**Modal:** `#hawalaModal` | **Handler:** `admin/includes/hawala_handler.php`

### Send Flow
| Step | Detail |
|------|--------|
| Select | Sender Customer + Main Account |
| Enter | Send amount + currency, Secret Code, Commission amount + currency, Notes |
| Validation | Commission currency must match send currency |
| System | Calculates net = send - commission; debits net from main account; debits sender wallet; creates `hawala_transfers` (status `pending`); records commission as `general_ledger` income; creates notification |

### Payout Flow — `processHawalaPayout()`
1. Verifies hawala ID + secret code
2. Creates `sarafi_transactions` (type `hawala_receive`)
3. Updates `hawala_transfers` status to `completed`
4. Credits receiver's `customer_wallets`

### Cancel — `cancelHawalaTransfer()`
- Only when status = `pending`
- Refunds sender wallet, reverses commission entry, sets cancelled

### Reverse Delete — `admin/delete_sarafi_hawala.php`
- Only for pending hawala; refunds wallet, restores main account

---

## 5. Currency Exchange

**Modal:** `#exchangeModal` | **Handler:** `admin/includes/exchange_handler.php`

| Step | Detail |
|------|--------|
| Select | Customer |
| Enter | From amount + currency, To amount + currency (auto-calc: `to = from × rate`), Rate (step 0.0001), Notes |
| Validation | Customer has sufficient balance in source currency wallet |

### Rate Resolution Algorithm — `getCurrentMarketRate()`
1. Try direct rate in `exchange_rates` table
2. Try inverse rate (swap currencies, invert)
3. If neither is USD: chain through USD (X → USD → Y)
4. If all fail: use provided rate as market rate (profit = 0)

### System Actions
- Calculates market rate and profit = `provided_to_amount - (from_amount × market_rate)`
- Creates `sarafi_transactions` (type `exchange`)
- Creates `exchange_transactions` record (from/to amounts, rate, profit)
- Debits source wallet, credits destination wallet
- Updates `exchange_rates` table with provided rate + inverse

### Reverse Delete — `admin/delete_sarafi_exchange.php`
- Adds back to source wallet, deducts from destination, deletes exchange and transaction records

---

## 6. Customer Management

| Feature | Page |
|---------|------|
| **Create Customer** | `admin/handlers/create_customer.php` — name, email, phone, address, optional initial balance |
| **Customer List** | `admin/customers.php` — includes sarafi quick actions |
| **Customer Detail** | `admin/customer_detail.php` — wallet balances per currency, last 100 sarafi transactions, direct deposit/withdrawal |

---

## 7. Database Tables

| Table | Purpose |
|-------|---------|
| `sarafi_transactions` | Core transaction records (type: deposit, withdrawal, hawala_send, hawala_receive, exchange, adjustment) |
| `customer_wallets` | Per-customer, per-currency wallet balances (unique on tenant+customer+currency) |
| `exchange_rates` | Currency exchange rate pairs with precision DECIMAL(15,6) |
| `exchange_transactions` | Detailed exchange operation records (from/to amounts, rate, profit) |
| `hawala_transfers` | Hawala transfer records (sender, receiver, secret code, commission, status) |
| `general_ledger` | Accounting entries — hawala commission income recorded here |

---

## 8. Technical Architecture

### Admin Pages: 1 main + 12 support files
| File | Purpose |
|------|---------|
| `admin/sarafi.php` | Main dashboard — stats, actions, transaction table |
| `admin/includes/sarafi_modals.php` | All modal definitions (customer, deposit, withdrawal, hawala, exchange) |
| `admin/includes/hawala_handler.php` | Hawala business logic (send, payout, cancel, commission) |
| `admin/includes/exchange_handler.php` | Exchange business logic (process, market rate, profit calc) |
| `admin/delete_sarafi_deposit.php` | Reverse deposit |
| `admin/delete_sarafi_withdrawal.php` | Reverse withdrawal |
| `admin/delete_sarafi_hawala.php` | Reverse hawala (pending only) |
| `admin/delete_sarafi_exchange.php` | Reverse exchange |
| `admin/view_sarafi_transaction.php` | AJAX — full transaction details JSON |
| `admin/ajax/get_customer_balance.php` | AJAX — per-currency wallet balances |
| `admin/ajax/cancel_hawala.php` | AJAX — cancel pending hawala |
| `admin/handlers/create_customer.php` | Create customer with optional initial balance |
| `admin/customer_detail.php` | Customer profile with sarafi transaction history |

### Super Admin: 1 file
`tenant_super_admin/sarafi.php` — cross-branch monitoring with stats, branch filter, search, pagination, detail modal

### Transaction Types (6)
deposit, withdrawal, hawala_send, hawala_receive, exchange, adjustment

### Currencies Supported
USD, AFS, EUR, AED/DARHAM (plus dynamic additional currencies from wallets)

### Access Roles
- admin, finance — full access
- tenant_super_admin — cross-branch monitoring
- Feature gated via `hasFeature('sarafi')`

### Security
- PDO prepared statements on all queries
- Database transactions for atomic operations (`beginTransaction`/`commit`/`rollback`)
- `DbSecurity::validateInput()` on delete scripts
- `SecureFileUpload` for receipts (max 10MB)
- Activity logging on all delete operations
- Running balance integrity maintained in `main_account_transactions`

### Multilingual
English, Dari (fa), Pashto (ps) — full translation sets for all sarafi strings
