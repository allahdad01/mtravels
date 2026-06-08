# 💱 M.Travels Sarafi (Currency Exchange) System — Features

A complete money exchange and customer wallet management system. Handle deposits, withdrawals, Hawala transfers, and currency exchanges with multi-currency support and full audit trail.

---

## 📊 Dashboard & Quick Actions

The main Sarafi dashboard gives you everything at a glance.

- **Currency Summary Cards** — See total customer wallet balances for USD, AFS, EUR, AED (and any other currencies), each with a color-coded card
- **5 Quick Action Buttons** — New Deposit, Withdrawal, Hawala Transfer, Currency Exchange, or New Customer — with keyboard shortcuts (`D`, `W`, `H`, `E`)
- **Transaction Table** — Paginated list of all transactions with date, customer, type (with icon), amount, currency, reference, and status
- **Color-Coded Rows** — Deposits in green, withdrawals in rose — easy to scan
- **Search** — Filter transactions by any text instantly
- **Transaction Details** — Click any transaction to see full info: customer details, amount, reference, status, notes, receipt image, and printable view
- **Edit Transactions** — Update amount, reference, and notes for deposits and withdrawals

---

## 💰 Deposits

Add funds to a customer's wallet.

1. Select the customer and main account
2. Enter the amount and currency (USD, EUR, AFS, AED)
3. Add an optional note and receipt file (image or PDF, up to 10MB)
4. Reference number is auto-generated with `DEP` prefix
5. System automatically credits the customer's wallet and the main account
6. Can be reversed if needed — all balances are properly restored

---

## 💳 Withdrawals

Withdraw funds from a customer's wallet.

1. Select the customer and main account
2. Enter the amount and currency
3. System checks that both the customer wallet and main account have sufficient balance
4. Reference number is auto-generated with `WDR` prefix
5. System debits the customer's wallet and main account
6. Can be reversed if needed

---

## 🔄 Hawala Transfers (Informal Money Transfer)

Send money from one customer to another using a secret code — perfect for remittances.

### Send Money
1. Select the sender customer and main account
2. Enter the amount, currency, and a secret code (shared with the receiver)
3. Optionally charge a commission fee
4. System deducts the net amount (send amount − commission) from the sender's wallet
5. Commission is recorded as income in the general ledger
6. Transfer status is set to "pending" — waiting for the receiver to claim

### Receive / Payout
1. Receiver provides the secret code
2. Operator verifies and processes the payout
3. Receiver's wallet is credited with the full send amount
4. Transfer status changes to "completed"

### Cancel
- Only pending transfers can be cancelled
- Sender's wallet is refunded, commission entry is reversed

---

## 💱 Currency Exchange

Exchange one currency for another within a customer's wallet.

1. Select the customer
2. Enter the source amount and currency, then the target amount and currency
3. System auto-calculates: target amount = source amount × exchange rate
4. Enter the exchange rate (up to 4 decimal places)
5. System validates the customer has enough balance in the source currency
6. **Smart Rate Lookup** — The system automatically finds the best available rate:
   - Checks for a direct rate pair
   - Tries the inverse rate
   - Chains through USD if neither is USD
   - Falls back to the rate you entered
7. Profit is calculated and recorded automatically
8. Source wallet is debited, destination wallet is credited
9. Rate is saved for future reference
10. Can be reversed if needed

---

## 👥 Customer Management

- **Create Customers** — Add new customers with name, email, phone, address, and optional initial wallet balance
- **Customer List** — View all customers (shared with the general customer database)
- **Customer Detail** — See wallet balances per currency and last 100 transactions; perform deposits/withdrawals directly from the customer profile
- **Wallet Balances** — Each customer has separate wallet balances for each currency

---

## 👑 Super Admin Monitoring

- **Cross-Branch Overview** — View all sarafi activity across branches
- **Summary Stats** — Total transactions, deposits, withdrawals, exchanges, with USD/AFS breakdowns
- **Branch Filter** — Focus on a specific branch
- **Search** — By customer name, reference number, or notes
- **Detail Modal** — Click any transaction for full details

---

## 🛡️ Security & Access Control

- **Role-Based Access** — Admin and Finance roles can manage sarafi; other roles cannot
- **Multi-Tenant & Branch Isolation** — Each branch sees only their own customers and transactions
- **Transaction Safety** — All financial operations run within database transactions to prevent data corruption
- **Audit Trail** — Every delete operation is logged
- **Receipt Uploads** — Secure file validation (type, size, max 10MB)
- **Balance Integrity** — Deleting a transaction properly restores all affected balances and adjusts subsequent records

---

## 💻 What You Get

| Resource | Count |
|----------|-------|
| Admin Pages | 1 main + 12 support files |
| Transaction Types | 6 (Deposit, Withdrawal, Hawala Send, Hawala Receive, Exchange, Adjustment) |
| Supported Currencies | USD, AFS, EUR, AED + dynamic |
| Keyboard Shortcuts | 4 (D, W, H, E) |
| Database Tables | 6 core tables |
| Delete/Reverse Scripts | 4 (deposit, withdrawal, hawala, exchange) |
| AJAX Endpoints | 3 (balance, cancel hawala, view transaction) |
| Receipt Upload | Image/PDF, max 10MB |
| Exchange Rate Precision | 4 decimal places (UI), 6 decimal places (DB) |
| Languages | 3 (English, Dari, Pashto) |

---

*Ready to manage your currency exchange operations? All features are available from the Sarafi dashboard.*
