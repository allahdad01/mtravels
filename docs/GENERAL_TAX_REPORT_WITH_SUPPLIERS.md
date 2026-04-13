# General Tax Report - Supplier Income & Tax Integration

## Overview
The General Tax Report now includes a comprehensive Supplier Income & Tax table that displays:
- Supplier Name
- Income Amount (USD) - Total profit from generated supplier report
- Exchange Rate
- Income (AFN) - Income converted using exchange rate (USD × Exchange Rate)
- Tax (4%) - 4% of the exchanged amount in AFN

## How It Works

### 1. Workflow
1. **Generate Individual Supplier Reports** first
   - Select suppliers
   - Choose quarter/year
   - Set exchange rate
   - Reports are saved to database with all data

2. **Generate General Tax Report**
   - Select quarter/year (must match supplier reports)
   - Automatically fetches saved supplier reports for that quarter
   - Displays supplier income/tax table
   - Add expenses and generate final report

3. **Export Report**
   - Excel or PDF with both sections
   - Supplier income and tax totals
   - Expense totals

### 2. Data Fetching Process

**New Function in Handler:** `getSavedReports()`
```php
GET handlers/quarterly_tax_handler.php?action=get_saved_reports&quarter=Q1&year=2024&branch_id=1
```

**Returns:**
```json
{
    "success": true,
    "data": [
        {
            "id": 1,
            "supplier_id": 5,
            "quarter": "Q1",
            "year": 2024,
            "report_data": {
                "supplier_id": 5,
                "supplier_name": "Airline A",
                "quarter": "Q1",
                "year": 2024,
                "data": [
                    {
                        "issue_date": "2024-01-15",
                        "full_name": "John Doe",
                        "sector": "DXB - JFK",
                        "details": {
                            "status": "Booked",
                            "pnr": "ABC123",
                            "base_price": 500,
                            "sold_price": 550,
                            "profit": 50
                        }
                    }
                    // ... more items
                ]
            }
        }
    ]
}
```

### 3. Calculation Logic

**Income (USD):** Sum of all profit fields from supplier report items
```
Total Profit = SUM(item.details.profit for each item)
```

**Income (AFN):** USD income × Exchange Rate
```
Income AFN = Total Profit USD × Exchange Rate
```

**Tax (4%):** 4% of exchanged amount
```
Tax = Income AFN × 0.04
```

## Files Modified

### 1. `admin/quarterly_tax_report.php`

#### Function: `generateGeneralReport()`
- **Lines 929-1005**
- Now fetches saved supplier reports via AJAX
- Calls `handlers/quarterly_tax_handler.php?action=get_saved_reports`
- Gracefully handles missing reports

#### Function: `displayGeneralReportPreview()`
- **Lines 1006-1121**
- Displays two sections:
  1. **Supplier Income & Tax Table**
     - Shows supplier name, income (USD), exchange rate, income (AFN), tax
     - Displays totals at bottom
  2. **Expenses Table**
     - Shows expense categories and amounts
     - Displays expense totals
- Includes helpful alert if no supplier reports found

### 2. `admin/handlers/quarterly_tax_handler.php`

#### New Function: `getSavedReports()`
- **Lines 433-476**
- Retrieves saved supplier reports for a quarter/year/branch
- Filters by tenant_id, quarter, year, and optionally branch_id
- Returns decoded JSON report data
- Handles database errors gracefully

#### Modified Function: `generateSupplierReport()`
- **Lines 338-369**
- Now saves generated report to `tax_reports` table
- Uses `INSERT ... ON DUPLICATE KEY UPDATE` for upsert behavior
- Ensures only one report per supplier/quarter/year/branch

### 3. `admin/handlers/quarterly_tax_export.php`

#### Modified Function: `exportGeneralReport()`
- **Lines 232-360**
- Now handles both supplier and expense data
- Creates two sections in Excel:
  1. **SUPPLIER INCOME & TAX section** (if suppliers exist)
  2. **EXPENSES section** (if expenses exist)
- Column widths adjusted for new data
- Proper formatting and totals
- Works for both XLSX and PDF formats

## Database Requirements

### Table: `tax_reports`
```sql
CREATE TABLE `tax_reports` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `tenant_id` INT NOT NULL,
    `supplier_id` INT NULL,
    `quarter` VARCHAR(3) NOT NULL,
    `year` INT NOT NULL,
    `report_type` ENUM('supplier', 'general') NOT NULL,
    `report_data` LONGTEXT NOT NULL,
    `branch_id` BIGINT(20) NULL,
    `created_by` INT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY `unique_supplier_report` (`tenant_id`, `supplier_id`, `quarter`, `year`, `branch_id`)
);
```

**Required Migration:** `006_alter_tax_reports_add_branch_id.sql`

## Data Flow Diagram

```
User selects quarter/year
         ↓
Fetches saved supplier reports
         ↓
Supplier reports loaded from DB
         ↓
User enters expenses
         ↓
Generates preview with:
  - Supplier Income & Tax table
  - Expense table
         ↓
Export to Excel/PDF
```

## Example Output (Excel)

```
================================================================================
GENERAL TAX REPORT
Period: Q1 2024

SUPPLIER INCOME & TAX
Supplier Name      | Income (USD) | Exchange Rate | Income (AFN) | Tax (4%)
Airline A          |    2,500.00  |      85       |  212,500.00  | 8,500.00
Hotel B            |    1,200.00  |      85       |  102,000.00  | 4,080.00
SUPPLIER TOTAL                                      314,500.00    12,580.00

EXPENSES
Category           | Amount (USD)
Office Rent        |      500.00
Utilities          |      200.00
EXPENSE TOTAL                     700.00
================================================================================
```

## Validation & Error Handling

1. **Missing Quarter/Year** - Error message
2. **Missing Expense Data** - Warning message (can still generate with suppliers only)
3. **No Supplier Reports** - Info alert suggesting user generate supplier reports first
4. **No Expenses** - Info alert, but can export supplier data only
5. **Database Errors** - Caught gracefully, continues with available data

## Browser Console Debug

When generating report, console shows:
```javascript
"GET /handlers/quarterly_tax_handler.php?action=get_saved_reports&quarter=Q1&year=2024&branch_id=1"
```

## Performance Considerations

- Supplier reports are fetched via AJAX (non-blocking)
- Only queries for specific quarter/year/branch
- UNIQUE constraint prevents duplicate reports
- Minimal database overhead for queries

## Future Enhancements

1. Add profit margin analysis
2. Supplier comparison charts
3. Monthly breakdown within quarter
4. Supplier performance metrics
5. Budget vs actual comparisons
