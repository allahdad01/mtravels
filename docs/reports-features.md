# Reports Module — Complete Feature Documentation

## Overview

The Reports module provides a unified report generator, financial dashboard with Chart.js visualization, quarterly tax reporting, HR analytics, compliance audits, and client/supplier financial statements. Multi-tenant, role-based, with PDF/Excel/Word/CSV export.

---

## 1. Core Report Generator

**Page:** `admin/report.php` (1,506 lines) | **JS:** `js/report/report.js` (849 lines)

A single-page report configuration hub with type/category/entity/date-range selection and three export formats.

### Report Types
| Type | Description | Available To |
|------|-------------|-------------|
| **General** | Aggregates across all business types | All roles |
| **Supplier** | Filtered by supplier entity | admin, finance |
| **Main Account** | Filtered by main account | admin, finance |
| **Client** | Filtered by client entity | admin, finance |

### Report Categories (16 total)
Each gated by the corresponding feature flag:
| Category | Feature Flag Key |
|----------|-----------------|
| Ticket | `ticket_bookings` |
| Ticket Reservation | `ticket_reservations` |
| Ticket Weight | `ticket_weights` |
| Refund Ticket | `refunded_tickets` |
| Date Change Ticket | `date_change_tickets` |
| Visa | `visa_applications` |
| Visa Refund | `visa_refunds` |
| Umrah | `umrah_bookings` |
| Umrah Refund | `umrah_refunds` |
| Hotel | `hotel_bookings` |
| Hotel Refund | `hotel_refunds` |
| Expense | `expense_management` |
| Creditor | `creditors` |
| Debtor | `debtors` |
| Additional Payment | `additional_payments` |
| Statement | `financial_statements` |

### Filters
- **Date Range** — Dual-calendar with presets: Today, Yesterday, Last 7/30 Days, This/Last Month, This/Last Year
- **Entity Selection** — AJAX-loaded dropdown (suppliers/clients/accounts)
- **Expense Category** — For expense reports
- **Umrah Family** — All families or specific family
- **Statement Currency** — USD or AFS

### Export Formats
All three available per report:
1. **PDF** — Dompdf
2. **Excel** — PhpSpreadsheet
3. **Word** — PhpWord

### API Endpoints
| File | Purpose |
|------|---------|
| `api/report/fetch_report_data.php` | Validates + queries report data |
| `api/report/export_report.php` | Generates PDF/Excel/Word output |
| `api/report/load_entities.php` | Loads suppliers/clients/accounts for dropdowns |
| `api/report/load_expense_categories.php` | Loads expense categories |
| `api/report/load_families.php` | Loads Umrah families |
| `api/report/get_entity_created_date.php` | Entity creation date for statement start |

---

## 2. Financial Dashboard (Embedded in report.php)

Visible to admin/finance roles only.

### Charts (Chart.js)
| Chart | Description |
|-------|-------------|
| **Income Overview** | Income by category — bar/line chart |
| **Expense Overview** | Expense by category — bar chart |
| **Profit/Loss Overview** | Combined profit vs loss visualization |

### Summary Cards (Dual-Currency: USD/AFS)
- Total Income (green)
- Total Expenses (red)
- Profit/Loss (dynamic — green for profit, red for loss)

### Quick Date Range Buttons
Today, This Week, This Month, This Quarter, This Year

### Chart Export
- Export as PNG image
- Export as Excel

### Comprehensive Financial Export
One-click multi-sheet Excel report via `api/expense/export_comprehensive_report.php` (2,584 lines)

### Supporting APIs
| File | Purpose |
|------|---------|
| `api/expense/get_financial_data.php` | Fetches all income/expense/PL data (320 lines) |
| `api/expense/export_comprehensive_report.php` | Multi-sheet financial Excel workbook |

---

## 3. Quarterly Tax Report

**Page:** `admin/quarterly_tax_report.php` (2,290+ lines) — admin only

Purpose: Afghan tax compliance — 4% tax on supplier profits converted to AFN.

### Report Type Selector
Ticket, Visa, Umrah, Hotel, All Types

### Tab Structure
| Tab | Description |
|-----|-------------|
| **Individual Supplier** | Select suppliers, data source (Actual or Random), exchange rate, per-supplier tax breakdown |
| **General Tax Report** | Consolidated across all suppliers + expense categories; income/tax table + expense summary |
| **Saved Reports** | Browse, view, export (Excel/PDF), and delete previously saved quarterly reports |

### Supplier Data Sources
- **Actual Data** — Real transaction data from database
- **Random Data** — Simulated data for testing (configurable min/max profit and item count)

### Tax Calculation
1. Profit extracted per transaction
2. Converted to AFN using specified exchange rate
3. 4% tax applied on exchanged amount
4. Displayed as: Subtotal (USD) → Exchanged (AFN) → Tax Due (AFN)

### Expense Management (General Tab)
- Category checkboxes
- Dynamic loading of expense items per category
- Per-item checkbox inclusion
- Inline "Add New Expense" form with category autocomplete
- Session-based temporary ad-hoc expenses

### Save & Recall
- Reports saved to database by year/quarter
- Viewable, exportable (Excel/PDF), deletable

### Handler Files
- `admin/handlers/quarterly_tax_handler.php` — server-side processing
- `admin/handlers/quarterly_tax_export.php` — export generation

---

## 4. HR Reports

**Page:** `admin/hr_reports.php` (719 lines) — admin only

**API:** `api/employee/generate_hr_report.php` (700 lines)

### Statistics Cards
- Total Employees (blue)
- Active Employees (green)
- Terminated Employees (red)
- New Hires This Year (purple)

### Report Types (4)
| Report | Content | Formats |
|--------|---------|---------|
| **Employee Overview** | Name, email, phone, role, hire date, status, base salary | CSV, PDF, Excel |
| **Termination Summary** | Termination records with reasons | CSV, PDF, Excel |
| **Role Distribution** | Active employees grouped by role with % breakdown | CSV, PDF, Excel |
| **Tenure Analysis** | Tenure in months, new hires today/this year | CSV, PDF, Excel |

### Generation Modal
Select format (CSV/PDF/Excel), optional "Include Charts" toggle

---

## 5. Compliance Reports

**Page:** `admin/compliance_report.php` (499 lines) — admin only

Sources data from `chat_audit_log` table.

### Report Types (5)
| Type | Use Case |
|------|----------|
| **GDPR** | User data access and processing activities |
| **HIPAA** | Communication logs (send/read message actions) |
| **SOX** | Financial communication and access logs |
| **Failed Access** | Denied/failed/error access attempts (security monitoring) |
| **Activity** | Action/status summary with unique user counts |

### Filters
- Date range (start/end)
- Report type selector buttons

### Export
CSV via `?export=1` query parameter

---

## 6. Client/Supplier Statements

| File | Purpose |
|------|---------|
| `api/report/generateStatement.php` | Generates statement data with opening balance, running balance, categorized transactions (418 lines) |
| `api/report/export_statement.php` | Exports to PDF/Excel |
| `admin/fetch_statement.php` | AJAX endpoint for client/supplier statements (344 lines) |
| `admin/print_statement.php` | Printable Sarafi customer statement with wallet balances (700 lines) |

### Statement Data Sources
- Client transactions: ticket sales, refunds, date changes, visa, umrah, hotel, funds
- Supplier transactions: supplier_transactions, funding_transactions
- Multi-currency: USD/AFS

---

## 7. Technical Architecture

### Admin Pages: 9 files
| File | Lines | Purpose |
|------|-------|---------|
| `admin/report.php` | 1,506 | Main report generator + financial dashboard |
| `admin/quarterly_tax_report.php` | 2,290+ | Quarterly tax compliance |
| `admin/hr_reports.php` | 719 | HR analytics |
| `admin/compliance_report.php` | 499 | Audit/compliance |
| `admin/generate_report.php` | 228 | Legacy single-supplier report |
| `admin/download_report.php` | 75 | Secure file download |
| `admin/print_statement.php` | 700 | Printable statement |
| `admin/fetch_statement.php` | 344 | AJAX statement endpoint |
| `admin/dashboard.php` | 1,137 | Main dashboard with KPIs |

### API Endpoints: 16 files
| Category | Count | Files |
|----------|-------|-------|
| Report Generator | 6 | `fetch_report_data.php`, `export_report.php`, `load_entities.php`, `load_expense_categories.php`, `load_families.php`, `get_entity_created_date.php` |
| Statements | 2 | `generateStatement.php`, `export_statement.php` |
| HR | 1 | `generate_hr_report.php` |
| Financial Dashboard | 4 | `get_financial_data.php`, `export_comprehensive_report.php`, `export_financial_data.php`, `export_expenses.php` |
| Expense Reports | 3 | `generate_category_pdf.php`, `print_expense.php`, `expense_actions.php` |

### JavaScript: 4 files
| File | Purpose |
|------|---------|
| `js/report/report.js` | Core report generation, filtering, export, statement logic |
| `js/expense/report_filter.js` | Date range picker, quick buttons, comprehensive export trigger |
| `js/expense/chart.js` | Chart.js initialization for Income/Expense/PL charts |
| `js/expense/expense_management.js` | Duplicate chart functions for management page |

### Export Libraries
| Library | Format |
|---------|--------|
| Dompdf | PDF (standard reports) |
| mPDF | PDF (HR reports) |
| PhpSpreadsheet | Excel |
| PhpWord | Word |
| html2pdf.js | Client-side PDF (quarterly tax) |
| XLSX.js | Client-side Excel (quarterly tax) |
| Chart.js export | PNG (chart images) |

### Access Roles
| Role | Report Access |
|------|--------------|
| admin | Full — all report types, financial dashboard, tax, HR, compliance, statements |
| finance | Report generator, financial dashboard, statements |
| sales, umrah | Report generator (limited categories) |

### Feature Gating
- Each report category gated by its feature flag (e.g., `ticket_bookings`, `visa_applications`)
- Financial dashboard visible to `admin`/`finance` only
- Tax/HR/Compliance — admin only
- `reports` feature flag in `features-helper.php` as "Dashboards & Reporting"

### Security
- CSRF protection on form submissions
- Role-based page access enforcement
- Multi-tenant/branch isolation on all queries
- `download_report.php` — secure file serving with path validation
