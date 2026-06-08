# 💰 M.Travels Accounts Module — Features

A centralized financial dashboard for managing all your agency's money — internal accounts, supplier balances, and client accounts — all in one place with multiple currencies and real-time balance tracking.

---

## 🏦 Three Account Types, One Dashboard

### 1️⃣ Internal / Main Accounts (Admin Only)

Your agency's own bank or cash accounts. Each account tracks balances in 4 currencies:

| Currency | Code |
|----------|------|
| US Dollar | USD ($) |
| Afghan Afghani | AFS (؋) |
| Euro | EUR (€) |
| UAE Dirham | DARHAM (د.إ) |

- **Add Accounts** — Create internal (cash) or bank accounts with optional bank account numbers for USD and AFS.
- **Edit Accounts** — Update name, type, bank details anytime.
- **Quick Fund** — Add money to any account directly from the card — select currency, enter amount, add remarks.
- **Health Gauge** — Each card shows a visual progress bar indicating account health.
- **Last Updated** — See when each account was last modified.

### 2️⃣ Supplier Accounts

See all your active suppliers with their current balances.

- **Balance Overview** — Stats strip shows total USD, total AFS, and amounts due across all suppliers.
- **Smart Filters** — Filter by category (Ticket, Visa, Umrah, Hotel), currency (USD/AFS), or balance type (positive/negative).
- **Inline Search** — Search any supplier by name instantly.
- **Quick Actions** — Fund a supplier, add a bonus, withdraw funds, view transaction history, or toggle active/inactive status.

### 3️⃣ Client Accounts

View all active clients and their outstanding balances in USD and AFS.

- **Balance Filters** — Show clients with positive, negative, or zero balance.
- **Inline Search** — Find any client by name.
- **Quick Actions** — Make a payment on behalf of a client, view transaction history, or toggle active/inactive status.

---

## 📊 Quick Stats Bar

A sticky bar at the top shows live totals for:
- Main account USD & AFS balances (admin only)
- Supplier USD & AFS totals
- Client due amounts in USD & AFS
- Total active accounts across all types

---

## ⚠️ Low Balance Alerts

- **Supplier Warning** — Amber alert when any supplier balance drops below $500 USD or 20,000 AFS.
- **Client Danger** — Red alert when any client owes more than $1,000 USD or 20,000 AFS.
- Dismiss alerts individually.

---

## 💳 Funding Operations

- **Fund Main Account** — Add money to any main account with currency selection and remarks.
- **Fund Supplier** — Pay a supplier from any main account, with automatic exchange rate conversion if currencies differ.
- **Client Payment** — A simple 7-step wizard to record client payments: choose balance currency, payment currency, enter amount with live conversion preview, select source account, add receipt and remarks.

---

## 🎁 Supplier Bonus

Add a bonus amount to any supplier account — useful for commissions, incentives, or adjustments.

---

## 💸 Withdraw & Transfer

- **Withdraw from Supplier** — Move money from a supplier back to a main account.
- **Transfer Between Accounts** — Move money between any two main accounts, even in different currencies (exchange rate applied automatically).

---

## 📋 Transaction History

Every account has a full transaction history accessible via a modal:

- **Main Account History** — Filter by currency, receipt number, or date range.
- **Supplier History** — Filter by receipt or date range. See running balance after each transaction.
- **Client History** — Filter by currency, receipt, or date range.
- **Print / Export PDF** — Print any transaction table with a single click.
- **Edit Transactions** — Update amount, description, or receipt number.
- **Delete Transactions** — Admin only, with confirmation.

---

## 🔐 Access Control

| Role | What They Can Do |
|------|-----------------|
| **Admin** | Full control — view/manage main accounts, fund/withdraw/transfer, toggle status, delete transactions |
| **Finance** | View all sections, fund suppliers/clients, view transactions |
| **Other Roles** | No access |

---

## 🌐 Multi-Currency Support

| Currency | Symbol | Balance Column |
|----------|--------|---------------|
| US Dollar | $ | `usd_balance` |
| Afghan Afghani | ؋ | `afs_balance` |
| Euro | € | `euro_balance` |
| UAE Dirham | د.إ | `darham_balance` |

Exchange rate conversion is automatic when funding or transferring between different currencies.

---

## 📱 Language Support

Available in all 3 languages: English, Dari (فارسی), and Pashto (پښتو).

---

## 🔗 Integration with Other Modules

The Accounts module is the financial backbone of M.Travels — every revenue and expense flows through it:

| Module | How It Connects |
|--------|----------------|
| **Ticket** | Ticket sales, refunds, date changes, weight sales |
| **Visa** | Visa sales and refunds |
| **Umrah** | Umrah bookings and refunds |
| **Hotel** | Hotel bookings and refunds |
| **Expense** | Expenses and budget allocations |
| **Salary** | Salary payments |
| **Debtors** | Money owed to you |
| **Creditors** | Money you owe |
| **Sarafi** | Deposit, hawala, withdrawal |
| **Additional Payments** | Non-standard income |
| **Dashboard** | Real-time financial overview |
| **Reports** | Comprehensive financial reporting |

---

## 🎓 Built-In Tutorials

The Accounts page includes 6 embedded video tutorials accessible via the "Watch Tutorials" button:
1. View Account Balances
2. Search & Filter Accounts
3. Add Account Funds
4. Withdraw Account Funds
5. View Transaction History
6. Manage Account Status

---

## 💻 What You Get

| Resource | Count |
|----------|-------|
| Main Management Page | 1 (1,215 lines) |
| Modal Templates | 13 |
| JavaScript Modules | 8 (2,844 lines total) |
| API Endpoints | 23 |
| CSS Files | 2 (1,499 lines total) |
| Database Tables | 2 dedicated + 9 linked |
| Supported Currencies | 4 |
| Languages | 3 |
| Balance Alert Types | 2 (supplier + client) |
| Built-In Tutorials | 6 |
| Transaction Types Tracked | 28 |

---

*Your complete financial command center — all accounts, all currencies, all in one place.*
