# 🚚 M.Travels Suppliers Module — Features

Manage every vendor, provider, and partner your agency works with — airlines, hotels, visa processors, Umrah service providers, and more. Track balances, make payments, record bonuses, handle penalties, and see the full transaction history for every supplier.

---

## 👤 Manage Suppliers

- **Add a Supplier** — Register any vendor with their name, contact person, phone, email, address, and currency (USD or AFS). Choose whether they're Internal (in-house) or External (third-party), and assign a category to control which modules can use them.
- **Edit Details** — Update any supplier information anytime.
- **Activate / Deactivate** — Toggle supplier status without losing their history.
- **Delete a Supplier** — Permanently remove a supplier record.

---

## 🏷️ Supplier Categories

Every supplier is assigned a category that determines where they appear:

| Category | Used In |
|----------|---------|
| **Ticket** | Ticket bookings, reservations, refunds, date changes, weights |
| **Visa** | Visa applications and refunds |
| **Umrah** | Umrah bookings and services |
| **Hotel** | Hotel bookings and refunds |
| **All** | Every module |

This keeps your supplier lists clean and relevant — ticket suppliers only show up in ticket forms, visa suppliers in visa forms, etc.

---

## 🔄 Internal vs External Suppliers

- **External Suppliers** — Third-party vendors (airlines, hotels, etc.) with full balance tracking and funding controls.
- **Internal Suppliers** — In-house or agency-owned entities. Fund/withdraw/bonus buttons are hidden on the Accounts page since these follow different financial flows.

---

## 💰 Financial Operations

All supplier financial actions are available from the **Accounts** page under the Supplier Accounts section:

### Fund a Supplier
Pay a supplier from any of your main accounts. If the payment currency differs from the supplier's currency, the exchange rate is applied automatically. Creates a credit in the supplier's balance and a debit in your main account.

### Add a Bonus
Credit a supplier's balance without deducting from any main account — useful for commissions, incentives, or adjustments.

### Withdraw from a Supplier
Move money from a supplier back to a main account — useful for refunds or corrections.

### View Transaction History
Every financial move is recorded in a paginated, filterable transaction log. Search by receipt number or date range. Print or export any transaction table as PDF.

---

## ⚠️ Low Balance Alerts

The Accounts page automatically warns you when a supplier's balance drops too low:
- **USD suppliers**: Alert when balance falls below $500
- **AFS suppliers**: Alert when balance falls below 20,000 AFs

These alerts appear as amber banners at the top of the page.

---

## 📋 Supplier Detail Page

Click into any supplier to see:
- Full profile information
- KPI cards: Total Credit, Total Debit, Current Balance, Transaction Count
- Complete transaction history with running balances

---

## 🔗 Client-to-Supplier JV Payments

Transfer money directly from a client's balance to a supplier's balance — bypassing main accounts entirely. Useful for pass-through payments where a client owes a supplier directly. Full reverse/delete support is available.

---

## 💸 Supplier Penalties

When processing refunds or changes, you can record supplier penalties (deductions charged by the supplier). These are supported across:

| Module | Where Penalty Appears |
|--------|----------------------|
| **Ticket Refund** | Supplier penalty deducted from refund |
| **Ticket Date Change** | Supplier penalty on rebooking |
| **Visa Refund** | Supplier penalty + service penalty |
| **Hotel Refund** | Supplier penalty on cancellation |
| **Umrah Refund** | Multi-supplier penalty handling |

---

## 📊 Quarterly Tax Reports

Generate per-supplier tax reports with profit data. Export to PDF or Excel. Save, search, and manage individual supplier tax reports for compliance.

---

## 🔐 Access Control

| Role | What They Can Do |
|------|-----------------|
| **Admin** | Full control — add, edit, delete, fund, bonus, withdraw, toggle status |
| **Finance** | Full management — add, edit, fund, bonus, withdraw |
| **Other Roles** | No access |

---

## 🌐 Multi-Currency Support

| Currency | Code |
|----------|------|
| US Dollar | USD ($) |
| Afghan Afghani | AFS (؋) |

Exchange rate conversion is automatic when funding suppliers in a different currency.

---

## 📱 Language Support

Available in all 3 languages: English, Dari (فارسی), and Pashto (پښتو).

---

## 💻 What You Get

| Resource | Count |
|----------|-------|
| Admin Management Page | 1 |
| Admin Detail Page | 1 |
| API Endpoints (dedicated) | 13 |
| Cross-Module API Endpoints | 8+ |
| Modal Templates | 6 |
| JavaScript Modules | 2 dedicated + 5 cross-module |
| Database Tables | 2 dedicated + 10 linked |
| Supported Currencies | 2 (USD, AFS) |
| Languages | 3 |
| Super Admin Page | 1 |
| Financial Operations | 3 (Fund, Bonus, Withdraw) |
| Supplier Categories | 5 (ticket, visa, umrah, hotel, all) |
| Transaction Types Tracked | 21 |

---

*Your complete supplier management hub — from registration to payments to tax reporting.*
