# 👤 M.Travels Clients Module — Features

Manage every person or agency that purchases travel services from you — track their balances, record payments, view complete transaction histories, process client-to-supplier payments, and give them access to their own client portal.

---

## 👥 Manage Clients

- **Add a Client** — Register any client with their name, email, phone, password, address, and opening balances in USD and AFS. Choose whether they're a Regular client or an Agency.
- **Edit Details** — Update name, email, phone, address, type, or status anytime.
- **Activate / Deactivate** — Control client access without losing their history.
- **Delete a Client** — Permanently remove a client record.

---

## 🔄 Regular vs Agency Clients

The system treats these two types differently:

| Feature | Regular Client | Agency Client |
|---------|---------------|---------------|
| **Balance Tracking** | Real-time — every booking and payment updates their balance | Informational only — balances are not auto-updated |
| **Refunds** | Balance is adjusted automatically | Transaction recorded but balance unchanged |
| **Payment Status** | No indicators on booking cards | Shows Red (unpaid), Yellow (partial), Green (paid) |
| **Booking Form** | Current balance auto-fetched and displayed | Balance not fetched |

---

## 💳 Client Payments (Fund Account)

Add money to a client's account from any of your main accounts with a simple 7-step wizard:

1. Choose balance currency (USD or AFS)
2. Choose payment currency
3. Exchange rate (only shown when currencies differ)
4. Enter amount with live conversion preview
5. Select which main account to draw from
6. Enter receipt number
7. Add optional remarks

The client's balance and your main account balance are both updated automatically.

---

## 📋 Transaction History

Every financial move involving a client is recorded in a detailed transaction log. View it from:

- **Client Detail Page** — See all transactions with running balances, broken down by type
- **Client History Modal** (from Accounts page) — Filter by currency, receipt number, or date range
- **Print / PDF** — Export any transaction table with one click

Transactions are linked to their source — ticket sales, visa applications, hotel bookings, Umrah packages, refunds, payments, and more — with human-readable descriptions.

---

## 🔗 Client-to-Supplier JV Payments

Transfer money directly from a client's balance to a supplier — bypassing your main accounts entirely. This is useful for pass-through payments where a client owes a supplier directly.

- **Create a JV Payment** — Deduct from client, credit to supplier
- **Full Reversal** — Reverse any JV payment with all balance corrections
- **Dual-Currency** — Supports different currencies between client and supplier

---

## ⚠️ Low Balance Alerts

The Accounts page warns you when clients owe significant amounts:
- **Red alert** when a client's USD balance drops below -$1,000
- **Red alert** when a client's AFS balance drops below -20,000 AFs

---

## 📊 Client Detail Dashboard

Click into any client to see a comprehensive dashboard:
- Contact info and account type
- USD and AFS balances
- Booking counts (tickets, visas, hotels, Umrah)
- Complete transaction history with per-type breakdowns

---

## 🌐 Client Portal

Every client gets their own login to a dedicated portal where they can:
- View their dashboard with balances and booking counts
- See their tickets, hotel bookings, visa applications, and Umrah packages
- Check payment history and download reports
- View refunds and additional payments

---

## 🔐 Access Control

| Role | What They Can Do |
|------|-----------------|
| **Admin** | Full control — add, edit, delete, fund, JV payments |
| **Finance** | Full management — add, edit, fund, JV payments |
| **Client** | Client portal — view own bookings and transactions |
| **Other Roles** | No access to admin client pages |

---

## 🌐 Multi-Currency Support

| Currency | Code |
|----------|------|
| US Dollar | USD ($) |
| Afghan Afghani | AFS (؋) |

Exchange rate conversion is automatic when funding clients in a different currency.

---

## 📱 Language Support

Available in all 3 languages: English, Dari (فارسی), and Pashto (پښتو).

---

## 💻 What You Get

| Resource | Count |
|----------|-------|
| Admin Client List Page | 1 |
| Client Detail Dashboard | 1 (1,263 lines) |
| API Endpoints (dedicated) | 6 |
| Cross-Module APIs | 5 |
| Modal Templates | 4 |
| JavaScript Modules | 2 dedicated |
| Database Tables | 2 dedicated + 8 linked |
| Supported Currencies | 2 (USD, AFS) |
| Languages | 3 |
| Super Admin Pages | 2 |
| Client Portal Pages | ~28 |
| JV Payment Pages | 3 |
| Transaction Types | 18+ |

---

*Your complete client management solution — from registration to payments to client self-service portal.*
