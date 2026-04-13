# Flexible Quarter Dates - Implementation

## Overview

The Quarterly Tax Report Generator now supports **flexible quarter dates**. Instead of being locked to calendar quarters (Jan-Mar, Apr-Jun, etc.), you can specify any custom date range for your reporting periods.

## Key Changes

### 1. Date Range Flexibility

**Previous Approach:**
- Quarters were hardcoded to calendar months
- Q1 = January, February, March
- Q2 = April, May, June
- Q3 = July, August, September
- Q4 = October, December

**New Approach:**
- Quarters can start/end on ANY date
- Support for fiscal years (e.g., April 15 - July 14)
- Support for custom business cycles
- Standard quarters still available as options

### 2. UI Changes

#### Supplier Report Tab
```
Select Quarter Period
├── Year (dropdown)
├── Quarter (Q1, Q2, Q3, Q4 buttons)
└── Custom Date Range (Optional)
    ├── Quarter Start Date (date picker)
    └── Quarter End Date (date picker)
```

#### General Tax Report Tab
```
Select Quarter Period
├── Year (dropdown)
├── Quarter (Q1, Q2, Q3, Q4 buttons)
└── Custom Date Range (Optional)
    ├── Quarter Start Date (date picker)
    └── Quarter End Date (date picker)
```

### 3. How It Works

**Option 1: Standard Quarters**
1. Select Year
2. Click a Quarter button (Q1, Q2, Q3, Q4)
3. System uses standard calendar quarter dates

**Option 2: Custom Date Ranges**
1. Leave Quarter unselected (optional)
2. Enter Quarter Start Date (e.g., Jan 15, 2024)
3. Enter Quarter End Date (e.g., Apr 14, 2024)
4. System fetches data for that exact date range

**Option 3: Mixed**
- Select Year and Quarter as defaults
- Override with custom dates if needed
- Custom dates take precedence over quarter selection

## Database Queries

### Standard Quarter Mapping
```
Q1: January 1 - March 31
Q2: April 1 - June 30
Q3: July 1 - September 30
Q4: October 1 - December 31
```

### Query Logic

**For Standard Quarters:**
```sql
WHERE DATE(transaction_date) BETWEEN '2024-01-01' AND '2024-03-31'
```

**For Custom Dates:**
```sql
WHERE DATE(transaction_date) BETWEEN '2024-01-15' AND '2024-04-14'
```

## API Endpoints

### Get Supplier Data with Flexible Dates

**Standard Quarter:**
```
GET /admin/handlers/quarterly_tax_handler.php
Action: get_supplier_data
Parameters:
  - supplier_id: INT
  - quarter: "Q1|Q2|Q3|Q4"
  - year: INT (YYYY)
```

**Custom Date Range:**
```
GET /admin/handlers/quarterly_tax_handler.php
Action: get_supplier_data
Parameters:
  - supplier_id: INT
  - date_from: "YYYY-MM-DD"
  - date_to: "YYYY-MM-DD"
```

### Get Expenses with Flexible Dates

**Standard Quarter:**
```
GET /admin/handlers/quarterly_tax_handler.php
Action: get_expenses
Parameters:
  - quarter: "Q1|Q2|Q3|Q4"
  - year: INT (YYYY)
  - categories: ARRAY (optional)
```

**Custom Date Range:**
```
GET /admin/handlers/quarterly_tax_handler.php
Action: get_expenses
Parameters:
  - date_from: "YYYY-MM-DD"
  - date_to: "YYYY-MM-DD"
  - categories: ARRAY (optional)
```

## Use Cases

### Fiscal Year Starting April 1
- Q1: April 1 - June 30
- Q2: July 1 - September 30
- Q3: October 1 - December 31
- Q4: January 1 - March 31

**In System:**
- Year: 2024
- Quarter Start: 2024-04-01
- Quarter End: 2024-06-30

### Mid-Month Quarters
- Q1: Jan 15 - Apr 14
- Q2: Apr 15 - Jul 14
- Q3: Jul 15 - Oct 14
- Q4: Oct 15 - Jan 14

**In System:**
- Year: 2024
- Quarter Start: 2024-01-15
- Quarter End: 2024-04-14

### Project-Based Quarters
- Business Cycle 1: Jan 1 - Mar 31
- Business Cycle 2: Apr 1 - Jun 30
- Business Cycle 3: Jul 1 - Sep 30
- Business Cycle 4: Oct 1 - Dec 31

**In System:**
- Year: 2024
- Quarter Start: 2024-01-01
- Quarter End: 2024-03-31

## JavaScript Implementation

### Data Collection
```javascript
// Get custom dates from form
const quarterStart = document.getElementById('supplierQuarterStart').value; // "2024-01-15"
const quarterEnd = document.getElementById('supplierQuarterEnd').value;     // "2024-04-14"

// Include in report data
supplierReportData = {
    year: year,
    quarter: quarter,
    quarterStart: quarterStart || null,
    quarterEnd: quarterEnd || null,
    suppliers: suppliers
};
```

### Date Validation
```javascript
// Validate date range if provided
if (quarterStart && quarterEnd && new Date(quarterStart) > new Date(quarterEnd)) {
    Swal.fire('Error', 'Quarter start date must be before end date', 'error');
    return;
}
```

### Report Display
```javascript
// Display period in preview
let periodDisplay = supplierReportData.quarterStart && supplierReportData.quarterEnd 
    ? `${supplierReportData.quarterStart} to ${supplierReportData.quarterEnd}`
    : `${supplierReportData.quarter} ${supplierReportData.year}`;
```

## Database Query Changes

### Original Query (Month-based)
```php
$months = $quarters[$quarter] ?? [];
$query = "WHERE MONTH(transaction_date) IN (" . implode(',', $months) . ")";
```

**Problem:** Locks to specific months, not flexible

### Updated Query (Date-based)
```php
$query = "WHERE DATE(transaction_date) BETWEEN ? AND ?";
$stmt->execute([$tenant_id, $supplier_id, $date_from, $date_to]);
```

**Advantage:** Supports any date range, completely flexible

## Benefits

✅ **Fiscal Year Support** - Handle fiscal years that don't match calendar
✅ **Custom Cycles** - Support business cycles starting on any date
✅ **Backward Compatible** - Standard quarters still work
✅ **Easy to Use** - Simple date pickers in UI
✅ **Flexible Reporting** - Generate reports for any period
✅ **No Database Changes** - Works with existing schema

## Testing

### Test Case 1: Standard Quarter
1. Select Year: 2024
2. Click Q1
3. Verify dates: 2024-01-01 to 2024-03-31

### Test Case 2: Custom Dates
1. Leave Quarter blank
2. Enter Start: 2024-01-15
3. Enter End: 2024-04-14
4. Verify correct data fetched

### Test Case 3: Override
1. Select Year: 2024 and Q1
2. Enter Start: 2024-02-01
3. Enter End: 2024-04-30
4. Verify custom dates are used (override Q1)

## Troubleshooting

### "No data showing"
- Verify date range has transactions in that period
- Check actual transaction dates in database
- Ensure date format is YYYY-MM-DD

### "Start date must be before end date"
- Verify start date is earlier than end date
- Check calendar popup for correct selection
- Dates use format YYYY-MM-DD

### "Invalid quarter"
- Only use Q1, Q2, Q3, Q4 for standard quarters
- Custom dates don't need quarter selection
- Can mix both (custom overrides quarter)

## Files Modified

1. **admin/quarterly_tax_report.php**
   - Added date pickers for both tabs
   - Updated JavaScript validation
   - Enhanced preview display

2. **admin/handlers/quarterly_tax_handler.php**
   - Updated `getSupplierData()` - supports date ranges
   - Updated `getExpenses()` - supports date ranges
   - Removed hardcoded month constraints
   - Added BETWEEN clause instead of MONTH()

## Summary

The system now provides **complete flexibility** for defining reporting periods:

- Use standard calendar quarters (original functionality)
- Use any custom date range (new functionality)
- Mix and match as needed
- No changes to database schema required
- Fully backward compatible

This enables support for:
- Fiscal years
- Mid-month cycles
- Project-based periods
- Custom business calendars
- Any other non-standard reporting period
