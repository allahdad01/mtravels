# 📋 M.Travels Debtors Module — Features

Track and manage everyone who owes money to your agency — customers with outstanding balances, installment buyers, and anyone with a debt agreement. Record debts, receive payments, print receipts, agreements, and always know who still owes you.

---

## 👤 Manage Debtors

- **Add a Debtor** — Register any person or company that owes you money. Record their name, email, phone, address, initial balance, currency (USD, AFS, EUR, or DARHAM), and optional agreement terms.
- **Edit Details** — Update contact information and agreement terms anytime. The balance and currency are locked after creation.
- **Deactivate / Reactivate** — Mark debtors as inactive (requires zero balance) or reactivate them.
- **Delete a Debtor** — Remove a debtor permanently (admin only, requires zero transactions).

---

## 💰 Record Payments Received

- **Log a Payment** — Record any payment received from a debtor with amount, date, description, and reference number.
- **Multi-Currency Payments** — Accept payment in any currency, even if it differs from the debtor's default. The system handles exchange rate conversion automatically.
- **Deposit to Account** — Choose which main account (USD, AFS, EUR, or DARHAM) the payment goes into.
- **Automatic Balance Updates** — Both the debtor's balance and your main account balance are updated in real time.

---

## 📄 View Transaction History

- **Debtor Detail Page** — See a debtor's full profile, agreement terms, and every transaction in one place.
- **Search & Filter** — Quickly find transactions by date, amount, or description.

---

## 🧾 Receipts, Statements & Agreements

- **Payment Receipt** — Print a professional receipt for any single payment received.
- **Full Statement** — Print a complete debtor statement with summary cards (initial debt, total paid, remaining balance, status) and a chronological timeline of all transactions.
- **Debt Agreement** — Print a formal debt agreement document with agency logo, debtor info, amount, terms & conditions, and signature lines for both parties.

---

## 📊 Dashboard Widget

- **At-a-Glance Total** — See total outstanding debtor balance on your main dashboard.
- **Quick Drill-Down** — Click to see debtors grouped by transaction type (ticket, visa, Umrah, hotel, etc.) in a popup modal.

---

## 📈 Reports & Exports

- **Debtor Report** — Generate reports filtered by date range with debtor details, balances, and payments.
- **Export Formats** — Download as PDF, Excel, or Word.
- **Statement Export** — Debtor transactions are included in client/supplier statement exports.

---

## 🔍 Super Admin Overview

- **Cross-Branch Dashboard** — View all debtors across every branch in your tenant from one screen.
- **Stats at a Glance** — Total debtors, active debtors, USD outstanding, AFS outstanding, and average debt.
- **Branch Filter** — Focus on a specific branch.
- **Quick Details** — Click any debtor to see full info and transactions with running balance in a popup modal.

---

## 🔐 Access Control

| Role | What They Can Do |
|------|-----------------|
| **Admin** | Full control — add, edit, receive payments, delete debtors and transactions |
| **Finance** | Manage payments and edit debtors — cannot delete |
| **Super Admin** | View-only across all branches |
| **Other Roles** | No access |

---

## 🌐 Multi-Currency Support

| Currency | Symbol |
|----------|--------|
| US Dollar | $ |
| Afghan Afghani | ؋ |
| Euro | € |
| UAE Dirham | د.إ |

Exchange rates are applied automatically when receiving payment in a different currency than the debtor's default.

---

## 📱 Language Support

Available in all 3 languages: English, Dari (فارسی), and Pashto (پښتو).

---

## 💻 What You Get

| Resource | Count |
|----------|-------|
| Debtor Management Page | 1 |
| Debtor Detail Page | 1 |
| API Handlers | 7 |
| Printable Documents | 3 (statement, receipt, agreement) |
| JavaScript Modules | 4 (including dashboard widget) |
| CSS Files | 1 |
| Database Tables | 2 dedicated + 3 linked |
| Supported Currencies | 4 |
| Languages | 3 |
| Super Admin Page | 1 |
| Dashboard Widget | 1 |
| Report Export Formats | 3 (PDF, Excel, Word) |

---

*Know who owes you and stay on top of collections — all from the Debtors dashboard.*
