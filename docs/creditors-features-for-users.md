# 📋 M.Travels Creditors Module — Features

Track and manage everyone your agency owes money to — suppliers, vendors, service providers, and more. Record debts, make payments, print receipts, and always know your outstanding balances.

---

## 👤 Manage Creditors

- **Add a Creditor** — Register any person or company you owe money to. Record their name, email, phone, address, initial balance, and currency (USD, AFS, EUR, or DARHAM).
- **Edit Details** — Update contact information anytime. The balance and currency are locked after creation.
- **Activate / Deactivate** — Mark creditors as active or inactive. Creditors are automatically deactivated when their balance reaches zero.
- **Delete a Creditor** — Remove a creditor permanently (admin only). A warning appears if there's still an outstanding balance.

---

## 💸 Make Payments

- **Record Payments** — Log any payment made to a creditor with amount, date, description, and reference number.
- **Multi-Currency Payments** — Pay in any currency, even if it differs from the creditor's default currency. The system handles the exchange rate conversion automatically.
- **Choose Payment Account** — Select which main account (USD, AFS, EUR, or DARHAM) the payment comes from.
- **Automatic Balance Updates** — Both the creditor's balance and your main account balance are updated in real time.

---

## 📄 View Transaction History

- **Creditor Detail Page** — See a creditor's full profile and every transaction in one place.
- **Filter & Search** — Quickly find transactions by date, amount, or description.

---

## 🧾 Receipts & Statements

- **Payment Receipt** — Print a professional receipt for any single payment with agency logo, creditor name, amount, date, description, and signature lines.
- **Full Statement** — Print a complete creditor statement with summary cards (initial balance, total paid, remaining balance, status) and a chronological timeline of all transactions.
- **"PAID" Watermark** — Statements for creditors with zero balance display a clear "PAID" watermark.

---

## 📊 Reports & Exports

- **Creditor Report** — Generate reports filtered by date range with columns: ID, name, phone, email, address, balance, currency, status, paid amount, received amount.
- **Export Formats** — Download as PDF or Excel.

---

## 🔍 Super Admin Overview

- **Cross-Tenant Dashboard** — View all creditors across every branch in your tenant from one screen.
- **Stats at a Glance** — Total creditors, active creditors, USD outstanding, AFS outstanding, and average credit.
- **Branch Filter** — Focus on a specific branch.
- **Quick Details** — Click any creditor to see full info and transactions in a popup modal.

---

## 🔐 Access Control

| Role | What They Can Do |
|------|-----------------|
| **Admin** | Full control — add, edit, pay, delete creditors and transactions |
| **Finance** | Manage payments and edit creditors — cannot delete |
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

Exchange rates are applied automatically when paying in a different currency than the creditor's default.

---

## 📱 Language Support

Available in all 3 languages: English, Dari (فارسی), and Pashto (پښتو).

---

## 💻 What You Get

| Resource | Count |
|----------|-------|
| Creditor Management Page | 1 |
| Creditor Detail Page | 1 |
| API Handlers | 2 |
| Printable Documents | 2 (statement + receipt) |
| JavaScript Modules | 6 |
| CSS Files | 1 |
| Database Tables | 2 dedicated + 3 linked |
| Supported Currencies | 4 |
| Languages | 3 |
| Super Admin Page | 1 |
| Report Export Formats | 2 (PDF, Excel) |

---

*Know who you owe and stay on top of your payments — all from the Creditors dashboard.*
