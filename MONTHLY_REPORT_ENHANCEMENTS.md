# Monthly Report Generator - Complete Enhancements

## Overview
Enhanced MonthlyReportGenerator.php with comprehensive error handling, complete service type coverage, and separated USD/AFS profit tracking across all reports.

---

## Features Implemented

### 1. **Complete Service Type Coverage (9 Types)**
All reports now display the following service types:
- ✅ Ticket Bookings
- ✅ Ticket Reservations
- ✅ Ticket Weights
- ✅ Refunded Tickets
- ✅ Date Changes
- ✅ Hotels
- ✅ Visas
- ✅ Umrah
- ✅ Additional Payments

### 2. **Currency Separation (USD & AFS)**
All profits are now displayed separately by currency:

**Financial Summary Section:**
- Ticket Bookings: $X.XX USD
- Ticket Reservations: $X.XX USD
- Ticket Weights: $X.XX USD
- Refunded Tickets: $X.XX USD + $Y.YY AFS
- Date Changes: $X.XX USD + $Y.YY AFS
- Hotels: $X.XX USD
- Visas: $X.XX USD
- Umrah: $X.XX USD
- Additional Payments: $X.XX USD

### 3. **Comprehensive Error Handling**
**Per-Service Error Logging:**
- Each service query wrapped in individual try-catch block
- Error messages include specific table name and error details
- Failed services return default zero values (don't stop report generation)
- Aggregated error summary logged for debugging

**Example Error Message:**
```
Error querying table 'ticket_bookings': [database error message]
Error querying table 'refunded_tickets' with JOIN to 'ticket_bookings': [error]
```

**Excel Error Display:**
If a service query fails, Excel shows:
- Red error text in the report
- Specific error message
- Report continues with other services

### 4. **Refunded Tickets Calculation (Corrected)**
Handles three calculation methods:
- **base**: Uses service_penalty as-is
- **sold**: Subtracts original ticket profit from penalty
- **default**: Uses service_penalty

Applied to both USD and AFS separately.

### 5. **All Reports Include Service Breakdown**

#### Excel Report (generateExcelReport)
- One sheet per branch
- Table with columns:
  - Service Type
  - Transactions count
  - Profit (USD)
  - Profit (AFS)
- All 9 service types listed
- Currency formatting applied

#### PDF Report (generatePDF)
- "Service Breakdown (by Branch)" section
- For each branch:
  - Branch name header
  - Table with Service Type | Transactions | Profit USD | Profit AFS
  - All 9 service types with USD/AFS separated
- Top Clients section
- Top Suppliers section

#### HTML Email (generateEmailHTML)
- Financial Summary with all 9 service types
- Top Branches by Revenue table
- Service Breakdown (by Branch) section
  - Separate sub-table for each branch
  - All services with USD/AFS separated
  - Error handling for missing data

---

## Database Queries Updated

### getFinancialSummary() Function
Returns all service types with USD and AFS separated:

**Fields returned:**
- ticket_profit (USD only)
- ticket_reservation_profit (USD only)
- ticket_weight_profit (USD only)
- hotel_profit (USD only)
- visa_profit (USD only)
- umrah_profit (USD only)
- additional_profit (USD only)
- refunded_tickets_usd_profit
- refunded_tickets_afs_profit
- date_change_usd_profit
- date_change_afs_profit

### getBranchServiceBreakdown() Function
Returns detailed breakdown per service with error logging:

For each service:
- service_type (name)
- count (transaction count)
- usd_profit
- afs_profit

With try-catch for each service query.

---

## Error Handling & Logging

### Individual Service Errors
Each service has its own try-catch block:
```php
try {
    // Query table
} catch (Exception $e) {
    error_log("Error querying table 'X': " . $e->getMessage());
    // Return default zero values
}
```

### Summary Error Logging
Aggregated at end of getBranchServiceBreakdown():
```
Service breakdown query errors for Tenant: 1, Branch: 1, Period: 2025-01-01 to 2025-01-31 | Errors: [list of all errors]
```

### Excel Error Display
If service breakdown fails:
- Red text error message
- Specific error details
- Report continues with other services

---

## Testing Checklist

- [x] Syntax validation (no PHP errors)
- [x] All 9 service types included
- [x] USD and AFS separated throughout
- [x] Excel shows 4 columns (Service Type | Transactions | USD | AFS)
- [x] PDF shows service breakdown with USD/AFS
- [x] HTML email shows service breakdown with USD/AFS
- [x] Error logging per service
- [x] Refunded tickets use correct calculation method
- [x] Date changes include USD/AFS
- [x] Financial summary complete

---

## Files Modified
- `cron/MonthlyReportGenerator.php`

## Functions Modified
1. `getFinancialSummary()` - Added 6 new fields (reservation, weight, refunded USD/AFS, date change USD/AFS)
2. `getBranchServiceBreakdown()` - Added individual try-catch per service
3. `generateExcelReport()` - Excel displays USD/AFS in columns
4. `generatePDF()` - PDF shows service breakdown with USD/AFS
5. `generateEmailHTML()` - HTML shows all services with USD/AFS

---

## Currency Handling

### USD Fields
Checked via: `CASE WHEN currency = 'USD'`
Profit/Penalty value used as-is

### AFS Fields
Checked via: `CASE WHEN currency = 'AFS'`
Profit/Penalty value used as-is

### Combined Display
Where applicable, shown as: `$X.XX USD + $Y.YY AFS`

---

## Notes

- Date format in reports: YYYY-MM-DD
- All monetary values formatted to 2 decimal places
- Error messages include table name for easy debugging
- If table doesn't exist, error caught and logged
- Report continues even if individual service fails
- Total profit = sum of all service profits (both currencies combined)
