# Excel Import Module — Complete Feature Documentation

## Overview

Bulk data import system supporting 9 entity types via `.xlsx`/`.xls` upload with automated entity resolution (supplier/client/account/family auto-creation), row-level error handling, date parsing (Excel serial + string), and `imported = 1` flagging on all inserted records.

---

## 1. Core Files

| File | Purpose |
|------|---------|
| `admin/excel_import.php` | Main UI page — drag-and-drop upload zone, data type chips, AJAX POST, progress bar, error display |
| `admin/excel_import_handler.php` | Backend handler (1,035 lines) — `ExcelImportHandler` class. Loads Excel via PhpSpreadsheet, dispatches to 9 processing methods |
| `admin/generate_excel_template.php` | Downloads `tenant_data_import_template.xlsx` with Instructions sheet + one data sheet per enabled feature |

---

## 2. Importable Data Types (9 Sheets)

| Sheet Name | Method | Columns |
|-----------|--------|---------|
| **Ticket Bookings** | `processTicketBookings()` | 20 (PNR, sector, airline, flight, date, passenger, supplier, sold_to, base_price, tax, total, paid, currency, sale_type, status, ticket_type, account, issue_date, description) |
| **Ticket Refunds** | `processTicketRefunds()` | 21 (PNR, airline, passenger, sector, refund_date, supplier, supplier_penalty, service_penalty, amount, currency, refund_type, status, account, remarks, calculation_method) |
| **Ticket Date Changes** | `processTicketDateChanges()` | 20 (PNR, old_date, new_date, old_flight, new_flight, supplier, supplier_penalty, service_penalty, amount, currency, status, account, remarks) |
| **Ticket Weights** | `processTicketWeights()` | 12 (PNR, weight_kg, rate, amount, currency, type, status, account, remarks) |
| **Ticket Reservations** | `processTicketReservations()` | 20 (PNR, sector, airline, flight, date, passenger, supplier, sold_to, base_price, tax, total, paid, currency, status, account, description) |
| **Visa Applications** | `processVisaApplications()` | 18 (passport_number, applicant_name, visa_type, destination, supplier, sold_to, price, paid, currency, status, account, remarks) |
| **Hotel Bookings** | `processHotelBookings()` | 18 (order_id, hotel_name, room_type, check_in, check_out, supplier, sold_to, total, paid, currency, status, account, remarks) |
| **Families** | `processFamilies()` | 14 (head_of_family, contact, passport, members_count, total_due, currency, status) |
| **Umrah Bookings** | `processUmrahBookings()` | 22 (name, package, supplier, members, total, paid, currency, status, account, service_type) |

---

## 3. Import Pipeline

1. **Upload** — MIME validation (`xlsx`/`xls`), 50MB max
2. **Load** — `PhpSpreadsheet\IOFactory::load()`
3. **Sheet Mapping** — 9 expected sheet names checked; missing sheets logged (non-fatal)
4. **Row Iteration** — Row-by-row from row 2 (header = row 1)
5. **Skip Logic** — `shouldSkipImportRow()` skips empty rows, rows starting with "NOTES" or "- "
6. **Entity Resolution** — Human-readable names → DB IDs:
   - `getOrCreateSupplier()` — creates placeholder if missing (`...@import.local`)
   - `getOrCreateClient()` — creates placeholder if missing
   - `getOrCreateMainAccount()` — creates placeholder if missing
   - `getOrCreateFamily()` — creates placeholder if missing
7. **Date Parsing** — `parseDate()` handles Excel serial numbers, `strtotime()` strings, empty values
8. **Insertion** — Parameterized SQL INSERT with `imported = 1` flag
9. **Result** — Returns `{ success, errors[], success_count, processed_sheets[] }`

---

## 4. Template Generator

**File:** `admin/generate_excel_template.php`

- Downloads: `tenant_data_import_template.xlsx`
- Contains **Instructions sheet** with usage guide
- Contains one data sheet per enabled feature (respects `hasFeature()` checks)
- Each sheet: header row (blue background, white bold), 1 sample data row, notes rows
- Uses PhpSpreadsheet `Style\Alignment`, `Style\Border`, `Style\Fill`, `Style\Font`

---

## 5. Library

**Dependency:** `phpoffice/phpspreadsheet: ^5.3` (installed: 5.4.0)

Used for both import (`IOFactory::load()`, `Date::excelToDateTimeObject()`) and export (report generation, statement export, comprehensive reports).

---

## 6. Database `imported` Flag

| Table | Column |
|-------|--------|
| `ticket_bookings` | `imported tinyint(1) DEFAULT 0` |
| `ticket_reservations` | `imported tinyint(1) DEFAULT 0` |
| `refunded_tickets` | `imported tinyint(1) DEFAULT 0` |
| `date_change_tickets` | `imported tinyint(1) DEFAULT 0` |
| `ticket_weights` | `imported tinyint(1) DEFAULT 0` |
| `visa_applications` | `imported tinyint(1) DEFAULT 0` |
| `hotel_bookings` | `imported tinyint(1) DEFAULT 0` |
| `umrah_bookings` | `imported tinyint(1) DEFAULT 0` |

---

## 7. Validation & Error Handling

| Area | Detail |
|------|--------|
| **File type** | MIME: `application/vnd.openxmlformats-officedocument.spreadsheetml.sheet` or `application/vnd.ms-excel` |
| **File size** | 50MB max (52,428,800 bytes) |
| **Row-level** | Each row in try/catch; errors collected in `$this->errors[]` array |
| **Empty rows** | Skipped via `shouldSkipImportRow()` |
| **Entity resolution** | Missing PNR for refund/date-change/weight throws Exception per row |
| **Auto-creation** | Missing suppliers/clients/accounts/families created with safe placeholder data |
| **Date parsing** | Excel serial, YYYY-MM-DD, or `strtotime()` formats |
| **No CSRF** | POST handler does not call `enforce_csrf()` |
| **No rate limiting** | POST handler does not call `enforce_rate_limit()` |
| **No audit log** | Import operations not persisted except `imported = 1` on records |

---

## 8. Role & Permission Model

| Role | Access |
|------|--------|
| `admin` | Full access |
| `finance` | Full access |
| `sales` | Full access |
| `umrah` | Full access |
| `staff` | No access (menu hidden) |

**Feature gate:** Not feature-gated — available to all non-staff roles.

---

## 9. Language Support

| Language | Key | Translation |
|----------|-----|-------------|
| English | `excel_import` | Excel Import |
| Dari/Farsi | `excel_import` | وارد کردن اکسل |
| Pashto | `excel_import` | د ایکسل واردول |

---

## 10. File Map

```
admin/
  excel_import.php                    # Main UI + POST handler
  excel_import_handler.php            # ExcelImportHandler class (1,035 lines)
  generate_excel_template.php         # Template downloader
  security.php                        # Auth enforcement
includes/
  nav_items.php                       # Menu entry (line 442)
  pricing-helper.php                  # Feature groups (import not gated)
  auth_check.php                      # staffCanSeeMenu()
  languages/en/common.php             # Translation
  languages/fa/common.php             # Translation
  languages/ps/common.php             # Translation
```
