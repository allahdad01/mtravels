# Quarterly Tax Report Generator - Final Implementation Summary

## Overview

The Quarterly Tax Report Generator is a comprehensive solution for admins to generate, manage, and export quarterly tax reports with support for multiple ticket types and exchange rate calculations.

## Features Implemented

### 1. **Tabbed Interface**
- **Individual Supplier Report**: Detailed per-supplier reporting with exchange rate conversion and 4% tax calculation
- **General Tax Report**: Aggregated expense reporting with category-based breakdowns

### 2. **Multi-Ticket Type Support**

The system now consolidates three different ticket types into a unified report:

#### a) **Regular Bookings** (ticket_bookings table)
- **Price Mapping**:
  - Base Price: `price` column
  - Sold Price: `sold` column
  - Profit: `profit` column

#### b) **Refunds** (refunded_tickets table)
- **Price Mapping**:
  - Base Price: `base` column
  - Sold Price: `sold` column
  - Profit: Always `0` (refunds have no profit)

#### c) **Date Changes** (date_change_tickets table)
- **Price Mapping**:
  - Base Price: `supplier_penalty`
  - Profit: `service_penalty`
  - Sold Price: `supplier_penalty + service_penalty`

### 3. **Data Fetching Logic**

The backend handler (`quarterly_tax_handler.php`) implements a unified fetching strategy:

```php
// Query all three ticket types with appropriate field mapping
1. Regular bookings with field renaming
2. Refunded tickets with field renaming
3. Date change tickets with calculated fields

// Combine all results
// Sort by issue_date in descending order
// Format data with ticket_type identifier
```

**Session & Branch Filtering**:
- All queries filter by `tenant_id` and `branch_id`
- `branch_id` is retrieved from `$_SESSION['branch_id']`
- Ensures multi-branch data isolation

**Date Filtering**:
- Regular bookings: `issue_date` column
- Refunds: `created_at` column
- Date changes: `created_at` column

### 4. **Tax & Exchange Rate Calculation**

The system applies calculations only to **totals**, not individual line items:

```
Total Profit (USD) = Sum of all ticket profits

Exchanged Amount = Total Profit × Exchange Rate
Example: $100 profit × 80 exchange rate = 8,000 AFN

Tax (4%) = Exchanged Amount × 0.04
Example: 8,000 AFN × 0.04 = 320 AFN
```

### 5. **Export Functionality**

#### Server-Side Export Handler (`quarterly_tax_export.php`)

- **Format Support**: XLSX (Excel) and PDF
- **Features**:
  - Professional formatting with colored rows
  - Column headers with blue background
  - Summary rows with distinct colors:
    - Light gray for USD totals
    - Yellow for exchange rates
    - Pink for tax amounts
    - Dark gray for grand totals
  - Number formatting for currency fields
  - Multi-supplier reports with individual and grand totals

#### Display vs Export Synchronization

Both the frontend display (`displaySupplierTickets`) and server-side export use:
- Same ticket data structure
- Same profit calculations
- Same exchange rate and tax logic
- Ticket type labels (Booking, Refund, Date Change)

### 6. **Database Schema**

Required tables:
- `ticket_bookings`: supplier (INT ID), price, sold, profit, issue_date
- `refunded_tickets`: supplier (INT ID), base, sold, created_at
- `date_change_tickets`: supplier (INT ID), supplier_penalty, service_penalty, created_at

Filtering columns:
- `tenant_id`: Isolates data by tenant
- `branch_id`: Isolates data by branch (ticket_bookings only)

## Frontend UI Components

### 1. **Individual Supplier Report Tab**

**Supplier Selection**:
- Checkboxes for selecting multiple suppliers
- Toggle options for data type (Actual/Random)
- Profit range configuration for random data

**Quarter Selection**:
- Quick buttons for Q1, Q2, Q3, Q4
- Custom date range picker for fiscal years

**Exchange Rate Input**:
- Configurable exchange rate for AFN conversion
- Applied to profit totals only

**Data Display Table**:
Columns:
- Issue Date
- Passenger Name
- Sector
- **Type Badge** (Booking/Refund/Date Change)
- Status Badge
- PNR Code
- Base Price
- Sold Price
- Profit (USD)

**Summary Rows**:
- Total (USD) - Light gray
- Exchange to AFN - Yellow
- Tax (4% of Exchanged) - Pink

### 2. **General Tax Report Tab**

**Category Selection**:
- Multi-select for expense categories
- Amount input fields per category

**Report Preview**:
- Simple table with category, amount columns
- Total row with bold formatting

## JavaScript Functions

### `generateSupplierReport()`
- Validates supplier selection
- Determines date range (quarter or custom)
- Calls backend handler to fetch data
- Populates `supplierReportData` object
- Displays preview using `displaySupplierTickets()`

### `displaySupplierTickets(supplierId, tickets)`
- Renders data table with all columns
- Maps ticket types to colored badges
- Calculates totals and exchange conversions
- Displays exchange rate and tax calculations

### `serverExport(reportType, format)`
- Sends report data via JSON POST
- Supports both XLSX and PDF formats
- Triggers browser download
- Shows success/error notifications

## API Endpoints

### `quarterly_tax_handler.php`

**Actions** (via POST with JSON body or POST/GET params):

1. **`generate_supplier_report`**
   - Parameters: supplier_id, data_type, quarter, year, branch_id, exchangeRate
   - Returns: Combined ticket data from all three types

2. **`generate_general_report`**
   - Parameters: quarter, year, expenses array
   - Returns: Report summary data

### `quarterly_tax_export.php`

**Query Parameters**:
- `report_type`: 'supplier' or 'general'
- `format`: 'xlsx' or 'pdf'

**POST Body** (JSON):
- Contains report data with suppliers, dates, exchange rate, etc.

## Data Flow Diagram

```
Frontend (quarterly_tax_report.php)
    ↓
    ├─→ User selects suppliers, dates, exchange rate
    ├─→ Calls generateSupplierReport()
    │
    ↓
Backend Handler (quarterly_tax_handler.php)
    ├─→ generate_supplier_report action
    │   ├─→ Query ticket_bookings (with field mapping)
    │   ├─→ Query refunded_tickets (with field mapping)
    │   ├─→ Query date_change_tickets (with field mapping)
    │   ├─→ Combine & sort by issue_date
    │   └─→ Return formatted data with ticket_type
    │
    ↓
Frontend Display
    ├─→ displaySupplierTickets() renders table
    ├─→ Adds ticket type badges
    ├─→ Calculates totals
    └─→ Shows exchange & tax rows

    │
    ├─→ User clicks Export
    │
    ↓
Export Handler (quarterly_tax_export.php)
    ├─→ Receives JSON payload with report data
    ├─→ Queries database for complete ticket data
    ├─→ Builds PhpSpreadsheet workbook
    ├─→ Applies formatting & styling
    └─→ Outputs XLSX or PDF file
```

## Key Implementation Details

### 1. **Ticket Type Identification**

Each ticket carries a `ticket_type` field:
```php
// In queries
SELECT ... , 'booking' as ticket_type FROM ticket_bookings
SELECT ... , 'refund' as ticket_type FROM refunded_tickets
SELECT ... , 'date_change' as ticket_type FROM date_change_tickets
```

### 2. **Profit Calculation Consistency**

All three tables contribute to profit calculations with their respective mappings:
- Bookings: Direct `profit` field
- Refunds: 0 (no profit extraction from refunds)
- Date Changes: `service_penalty` only (supplier_penalty is cost)

### 3. **Exchange Rate Application**

Applied only to the **total profit**, not individual items:
```javascript
const totalProfit = sum of all individual profits
const exchangedAmount = totalProfit * exchangeRate
const tax = exchangedAmount * 0.04
```

### 4. **Branch Isolation**

All queries include branch filtering:
```php
WHERE tenant_id = ? AND branch_id = ?
```

Ensures reports only contain data from the logged-in user's branch.

### 5. **Date Filtering Flexibility**

Supports both:
- Quarter-based (Q1-Q4 for any year)
- Custom date ranges (fiscal years, specific periods)

## Testing Checklist

- [ ] Admin login and navigation to Quarterly Tax Report
- [ ] Supplier selection with multiple choices
- [ ] Quarter selection and custom date range
- [ ] Exchange rate configuration
- [ ] Data display with ticket type badges
- [ ] Calculation accuracy (totals, exchange, tax)
- [ ] XLSX export with proper formatting
- [ ] PDF export generation
- [ ] Multi-supplier report consolidation
- [ ] General tax report generation
- [ ] Excel/PDF export for general reports
- [ ] Branch data isolation verification
- [ ] Session-based access control

## Files Modified

1. **admin/quarterly_tax_report.php**
   - Updated `displaySupplierTickets()` to show ticket type badges
   - Added "Type" column to table
   - Updated colspan values for summary rows

2. **admin/handlers/quarterly_tax_handler.php**
   - Added `ticket_type` field to formatted data response
   - Already had combined multi-table fetching logic

3. **admin/handlers/quarterly_tax_export.php**
   - Refactored to fetch from all three tables combined
   - Proper profit mapping for each ticket type
   - Updated column count to 9 (added Type)
   - Updated all cell references (H→I for profit columns)
   - Added type label mapping in export

## Performance Considerations

- Single database query per ticket type per supplier
- Results combined in PHP (memory efficient for typical dataset sizes)
- Sorting done in application layer
- No complex joins required
- Suitable for typical monthly/quarterly reporting volumes

## Future Enhancements

- Caching for repeated report generation
- Bulk export for multiple quarters
- Report templates customization
- Email delivery of reports
- Scheduled report generation
- Advanced filtering by passenger, route, etc.
