# 📊 M.Travels Reports System — Features

Generate professional reports across all business modules, visualize financial data with interactive charts, prepare tax documents, analyze HR metrics, and export everything in your preferred format.

---

## 📋 Report Generator

A single hub to create any report in the system.

- **Choose What to Report On** — 16 report categories covering every module: Tickets, Reservations, Weights, Refunds, Date Changes, Visas, Umrah, Hotels, Expenses, Creditors, Debtors, Additional Payments, and Financial Statements
- **Filter by Entity** — Generate reports for a specific Supplier, Client, or Main Account
- **Pick Any Date Range** — Use the calendar picker with quick presets: Today, Yesterday, Last 7 Days, Last 30 Days, This Month, Last Month, This Year, Last Year
- **Three Export Formats** — Every report can be downloaded as:
  - 📄 **PDF** — Professional print-ready format
  - 📗 **Excel** — Spreadsheet with full data
  - 📘 **Word** — Editable document format

---

## 📈 Financial Dashboard

A real-time financial overview embedded in the reports page — visible to admin and finance roles.

### Interactive Charts (Chart.js)
- **Income Overview** — See income broken down by category in a bar/line chart
- **Expense Overview** — View expenses by category
- **Profit/Loss Overview** — Compare profit vs loss at a glance

### Summary Cards
- **Total Income** (USD & AFS) — green
- **Total Expenses** (USD & AFS) — red
- **Profit/Loss** (USD & AFS) — dynamically switches between green (profit) and red (loss)

### Quick Date Filters
Today, This Week, This Month, This Quarter, This Year

### Export Options
- **Download Charts as PNG** — Save any chart as an image
- **Export Charts to Excel**
- **Comprehensive Financial Report** — One-click multi-sheet Excel workbook with all income sources, expenses by category, currency conversion, and profit/loss summary

---

## 🧾 Quarterly Tax Report

Prepare Afghan tax compliance reports (4% tax on supplier profits).

- **Choose Your Report Type** — Ticket, Visa, Umrah, Hotel, or All Types combined
- **Two Tabs for Flexibility**:
  1. **Individual Supplier** — Select specific suppliers, set exchange rate, see per-supplier profit and tax breakdown
  2. **General Tax Report** — Consolidated report across all suppliers plus expense categories
- **Actual or Test Data** — Use real transaction data or generate simulated data for testing
- **Tax Calculation** — System calculates: Profit → Convert to AFN → Apply 4% tax → Display clear breakdown
- **Add Expenses** — Include business expenses in the report with an inline expense form
- **Save for Later** — Save reports by year/quarter; view, export (Excel/PDF), or delete them anytime

---

## 👥 HR Reports

Analyze your workforce with four dedicated reports.

- **Statistics Cards** — See Total Employees, Active, Terminated, and New Hires This Year at a glance
- **4 Report Types**:
  1. **Employee Overview** — Full list with name, email, phone, role, hire date, status, and salary
  2. **Termination Summary** — Termination records with reasons
  3. **Role Distribution** — Active employees grouped by role with percentage breakdown
  4. **Tenure Analysis** — How long employees have been with the company, new hire counts
- **Choose Your Format** — CSV, PDF, or Excel
- **Optional Charts** — Toggle chart inclusion for PDF/Excel exports

---

## 🔒 Compliance Reports

Meet regulatory requirements with audit-ready reports.

- **5 Report Types**:
  1. **GDPR** — User data access and processing activities
  2. **HIPAA** — Communication logs
  3. **SOX** — Financial communication and access logs
  4. **Failed Access** — Security monitoring (denied/failed access attempts)
  5. **Activity** — General action/status summary
- **Date Range Filter** — Set any start/end date
- **Export to CSV** — Download for audit records

---

## 📑 Client & Supplier Statements

Generate professional financial statements.

- **Client Statements** — Full transaction history: ticket sales, refunds, date changes, visa, umrah, hotel, funds — with running balance
- **Supplier Statements** — Supplier transactions and funding history
- **Multi-Currency** — USD and AFS support
- **Printable** — Professional print layout with wallet balances and agency branding
- **Export** — PDF and Excel formats

---

## 🛡️ Security & Access Control

- **Role-Based Access** — Admin sees everything; finance sees financial reports and statements; sales/umrah see module-specific reports
- **Feature Gated** — Each report category appears only if the related feature is enabled in your subscription
- **Tenant & Branch Isolation** — Reports show only your branch's data
- **Secure Download** — All file downloads are validated and served securely

---

## 💻 What You Get

| Resource | Count |
|----------|-------|
| Admin Report Pages | 9 |
| API Endpoints | 16 |
| JavaScript Modules | 4 |
| Report Categories | 16 |
| Export Formats | 5 (PDF, Excel, Word, CSV, PNG) |
| Export Libraries | 6 (Dompdf, mPDF, PhpSpreadsheet, PhpWord, html2pdf.js, XLSX.js) |
| Chart Types | 3 (Income, Expense, Profit/Loss) |
| Quick Date Presets | 8 |
| Tax Features | 4 report types, actual/random data, save/recall |
| HR Report Types | 4 |
| Compliance Report Types | 5 |
| Languages | 3 (English, Dari, Pashto) |

---

*Ready to generate reports? All features are available from the Reports dashboard.*
