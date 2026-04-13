# Quarterly Tax Report - Testing & Verification Guide

## Pre-Testing Setup

1. Ensure you have admin credentials
2. Verify the following tables exist:
   - `ticket_bookings` (with columns: id, tenant_id, branch_id, supplier, price, sold, profit, issue_date, status, pnr, etc.)
   - `refunded_tickets` (with columns: id, tenant_id, supplier, base, sold, created_at, status, pnr, etc.)
   - `date_change_tickets` (with columns: id, tenant_id, supplier, supplier_penalty, service_penalty, created_at, status, pnr, etc.)
   - `suppliers` (with columns: id, name, tenant_id, status)
   - `expense_categories` (with columns: id, name, tenant_id)

3. Ensure sample data exists in each ticket type table for testing

## Test Scenarios

### Test 1: Individual Supplier Report - Basic Flow

**Steps:**
1. Navigate to Admin → Quarterly Tax Report
2. Click on "Individual Supplier Report" tab
3. Select a supplier with checkboxes
4. Select "Actual Data" option
5. Choose a quarter (e.g., Q1) and year
6. Set Exchange Rate to 80
7. Click "Generate Report"

**Expected Results:**
- [ ] Report preview loads without errors
- [ ] Table displays with 9 columns:
  - Issue Date
  - Passenger
  - Sector
  - **Type** (with colored badges: Booking=blue, Refund=yellow, Date Change=cyan)
  - Status
  - PNR
  - Base Price
  - Sold Price
  - Profit (USD)
- [ ] All three ticket types appear in the table (if data exists for each)
- [ ] TOTAL (USD) row shows sum of profits in light gray
- [ ] EXCHANGE TO AFN row shows: profit × 80 = AFN amount in yellow
- [ ] TAX (4% OF EXCHANGED AMOUNT) row shows: exchanged × 0.04 in pink

**Example Calculation Check:**
If Total Profit = $100:
- Exchanged: 100 × 80 = 8,000 AFN
- Tax: 8,000 × 0.04 = 320 AFN

### Test 2: Multiple Suppliers Report

**Steps:**
1. Select 2-3 suppliers with checkboxes
2. Generate report with same settings as Test 1

**Expected Results:**
- [ ] Data displays correctly for all selected suppliers
- [ ] Each supplier's data is properly combined
- [ ] Totals are accurate for each supplier group
- [ ] Ticket types from all suppliers are displayed

### Test 3: Custom Date Range

**Steps:**
1. Select a supplier
2. Toggle "Custom Date Range"
3. Set start date (e.g., 2024-01-01) and end date (e.g., 2024-03-31)
4. Generate report

**Expected Results:**
- [ ] Report only shows tickets within selected date range
- [ ] Dates displayed match the range

### Test 4: XLSX Export

**Steps:**
1. Generate a supplier report (following Test 1)
2. Click "Export to Excel" button
3. Save the file locally
4. Open in Excel or LibreOffice Calc

**Expected Results:**
- [ ] File downloads without errors
- [ ] Headers are bold with blue background
- [ ] All 9 columns present (including Type)
- [ ] Type column shows: "Booking", "Refund", or "Date Change"
- [ ] Numbers are formatted with commas (e.g., 1,234.56)
- [ ] Summary rows have proper colors:
  - Total (USD) = Light Gray
  - Exchange to AFN = Yellow
  - Tax = Pink
  - Grand Totals = Dark Gray
- [ ] All calculations match the preview

### Test 5: PDF Export

**Steps:**
1. Generate a supplier report (following Test 1)
2. Click "Export to PDF" button
3. Save the file locally
4. Open in PDF reader

**Expected Results:**
- [ ] PDF generates without errors
- [ ] Report content is readable
- [ ] All data is visible
- [ ] Headers and totals are formatted
- [ ] No missing data or truncation

### Test 6: Profit Calculation Verification by Ticket Type

**Booking Ticket Test:**
- [ ] Uses `profit` column from ticket_bookings directly
- [ ] Example: price=$100, sold=$120, profit=$20 ✓

**Refund Ticket Test:**
- [ ] Always shows profit = 0
- [ ] Uses `base` and `sold` from refunded_tickets
- [ ] Example: base=$100, sold=$95, profit=0 ✓

**Date Change Ticket Test:**
- [ ] Base Price = supplier_penalty
- [ ] Profit = service_penalty
- [ ] Sold Price = supplier_penalty + service_penalty
- [ ] Example: supplier_penalty=$10, service_penalty=$5:
  - Base Price = $10
  - Profit = $5
  - Sold Price = $15 ✓

### Test 7: Exchange Rate Variations

**Test with different exchange rates:**

| Exchange Rate | Total Profit | Exchanged | Tax (4%) |
|---|---|---|---|
| 80 | $100 | 8,000 AFN | 320 AFN |
| 90 | $100 | 9,000 AFN | 360 AFN |
| 100 | $100 | 10,000 AFN | 400 AFN |

**Steps:**
1. Generate same report 3 times with exchange rates: 80, 90, 100
2. Verify calculations match table above

### Test 8: General Tax Report Tab

**Steps:**
1. Click on "General Tax Report" tab
2. Select a quarter and year
3. Select expense categories (checkboxes)
4. Enter amounts for each category
5. Click "Generate Report"

**Expected Results:**
- [ ] Table displays with Category and Amount columns
- [ ] Total row shows sum of all amounts
- [ ] Timestamp shows generation time

### Test 9: General Report Export

**Steps:**
1. Generate general report (following Test 8)
2. Export to Excel and PDF

**Expected Results:**
- [ ] Both formats generate without errors
- [ ] Data matches preview
- [ ] Categories and amounts are correct
- [ ] Total is calculated correctly

### Test 10: Data Validation

**Steps:**
1. Try to generate report without selecting suppliers
2. Try to export without generating report first

**Expected Results:**
- [ ] Error message: "Please select at least one supplier"
- [ ] Error message: "Generate a report first"

### Test 11: Branch Isolation

**Prerequisites:** Must have two different branches in your tenant

**Steps:**
1. Login as admin from Branch A
2. Generate quarterly tax report
3. Logout
4. Login as admin from Branch B
5. Generate quarterly tax report for same supplier

**Expected Results:**
- [ ] Branch A sees only Branch A's data
- [ ] Branch B sees only Branch B's data
- [ ] No cross-branch data leakage
- [ ] Totals are different if data differs by branch

### Test 12: Random Data Generation

**Steps:**
1. Select a supplier
2. Choose "Random Data" option
3. Set profit range (e.g., $5 - $50)
4. Set item count (e.g., 10)
5. Generate report

**Expected Results:**
- [ ] Table displays 10 rows
- [ ] Each profit is between $5 and $50
- [ ] Data is randomized on each generation (except dates from actual records)
- [ ] Totals calculate correctly from random values

## Performance Tests

### Test 13: Large Dataset Performance

**Steps:**
1. Generate report for supplier with 500+ tickets
2. Measure load time

**Expected Results:**
- [ ] Report loads in under 5 seconds
- [ ] No timeout errors
- [ ] All data displayed correctly

### Test 14: Multi-Supplier Large Report

**Steps:**
1. Select 5 suppliers
2. Generate report for full quarter
3. Export to Excel

**Expected Results:**
- [ ] Processing completes without timeout
- [ ] Excel file generates and opens properly
- [ ] All supplier data included

## Data Integrity Tests

### Test 15: Consistency Check

**Steps:**
1. Generate report and note totals
2. Export to Excel
3. Compare Excel numbers with on-screen display

**Expected Results:**
- [ ] Export data matches screen preview exactly
- [ ] No rounding discrepancies
- [ ] All calculations identical

### Test 16: Edge Cases

**Steps:**
1. Test with $0 profit (should show $0 everywhere)
2. Test with negative profit (should handle correctly)
3. Test with very large numbers (>$999,999)
4. Test with decimal precision ($0.01)

**Expected Results:**
- [ ] All edge cases handled gracefully
- [ ] Formatting correct for all values
- [ ] No errors or display issues

## Security Tests

### Test 17: Access Control

**Steps:**
1. Try accessing report as non-admin user
2. Try accessing handler endpoints directly without admin session

**Expected Results:**
- [ ] Non-admin users redirected to login
- [ ] Handler returns 403 Unauthorized without session

### Test 18: Session Validation

**Steps:**
1. Generate report
2. Clear session cookies
3. Try to export

**Expected Results:**
- [ ] Export fails with authentication error
- [ ] Session is properly validated

## Browser Compatibility

### Test 19: Cross-Browser Testing

Test on:
- [ ] Chrome (latest)
- [ ] Firefox (latest)
- [ ] Safari (if on Mac)
- [ ] Edge

**Expected Results:**
- All features work identically across browsers
- Export downloads work properly
- Table display is consistent

## Verification Checklist

Before considering implementation complete:

- [ ] All syntax validation passes (PHP -l)
- [ ] No database errors in error logs
- [ ] All AJAX calls return proper JSON
- [ ] Export files contain all expected data
- [ ] Calculations are mathematically correct
- [ ] UI is responsive and user-friendly
- [ ] Error messages are clear and helpful
- [ ] Performance is acceptable (>5 sec for reasonable datasets)
- [ ] Security controls are in place
- [ ] Multi-language support (if applicable)
- [ ] Mobile responsiveness (if needed)

## Debugging Tips

If tests fail:

1. **Check Browser Console**: Look for JavaScript errors
2. **Check PHP Logs**: `/var/log/php-errors.log` or configured error log
3. **Check Network Tab**: Verify API calls are returning expected data
4. **Database Query Test**: Run queries directly in database client to verify data
5. **Session Verification**: Log session values to ensure branch_id is set
6. **Date Format**: Ensure dates are in YYYY-MM-DD format in database

## Sample Test Data SQL

If you need to create test data:

```sql
-- Insert test supplier
INSERT INTO suppliers (id, tenant_id, name, status) 
VALUES (999, 1, 'Test Supplier', 'active');

-- Insert test booking
INSERT INTO ticket_bookings 
(tenant_id, branch_id, supplier, price, sold, profit, issue_date, status, pnr, title, passenger_name, origin, destination)
VALUES (1, 1, 999, 100, 120, 20, '2024-01-15', 'Booked', 'ABC123', 'Mr.', 'John Doe', 'NYC', 'LAX');

-- Insert test refund
INSERT INTO refunded_tickets
(tenant_id, supplier, base, sold, created_at, status, pnr, title, passenger_name, origin, destination)
VALUES (1, 999, 100, 95, NOW(), 'Refunded', 'DEF456', 'Mr.', 'Jane Smith', 'NYC', 'LAX');

-- Insert test date change
INSERT INTO date_change_tickets
(tenant_id, supplier, supplier_penalty, service_penalty, created_at, status, pnr, title, passenger_name, origin, destination)
VALUES (1, 999, 10, 5, NOW(), 'Changed', 'GHI789', 'Mr.', 'Bob Johnson', 'NYC', 'LAX');
```

## Known Limitations

1. Reports process data in-memory; very large datasets (10,000+ rows) may be slow
2. PDF export depends on Dompdf; complex formatting may not be perfect
3. Decimal precision is limited to 2 places (currency standard)
4. Date filtering uses issue_date/created_at which may vary between systems

## Sign-Off

- [ ] All tests passed
- [ ] No critical issues found
- [ ] Ready for production deployment
- [ ] Date: _______________
- [ ] Tester: _______________
