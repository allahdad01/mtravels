# Quarterly Tax Report - Quick Reference Guide

## Files Modified

| File | Changes |
|------|---------|
| `admin/quarterly_tax_report.php` | Added Type column, ticket type badges in display function |
| `admin/handlers/quarterly_tax_handler.php` | Added ticket_type to response data |
| `admin/handlers/quarterly_tax_export.php` | Refactored to fetch all 3 ticket types, fixed profit mapping |

## Profit Mapping Quick Reference

### Booking Tickets
- **Table:** `ticket_bookings`
- **Base:** `price` column
- **Sold:** `sold` column  
- **Profit:** `profit` column
- **Date Field:** `issue_date`

### Refund Tickets
- **Table:** `refunded_tickets`
- **Base:** `base` column
- **Sold:** `sold` column
- **Profit:** Always 0 (no profit extraction)
- **Date Field:** `created_at`

### Date Change Tickets
- **Table:** `date_change_tickets`
- **Base:** `supplier_penalty` column
- **Sold:** `supplier_penalty + service_penalty`
- **Profit:** `service_penalty` column
- **Date Field:** `created_at`

## Tax Calculation Formula

```
Step 1: Calculate total profit (sum of all individual profits)
Step 2: Apply exchange rate: Total Profit × Exchange Rate = Exchanged Amount
Step 3: Calculate tax: Exchanged Amount × 0.04 = Tax in AFN
```

**Example with real numbers:**
```
Booking profit:    $20.00
Refund profit:     $0.00
DateChange profit: $5.00
─────────────────────────
Total:             $25.00

Exchange rate: 80 AFN/USD
Exchanged: 25 × 80 = 2,000 AFN
Tax: 2,000 × 0.04 = 80 AFN
```

## API Endpoint Reference

### Generate Supplier Report
```
POST /admin/handlers/quarterly_tax_handler.php
Content-Type: application/json
Action: generate_supplier_report

{
  "suppliers": [{"id": 123, "name": "Supplier A"}],
  "quarter": "Q1",
  "year": 2024,
  "quarterStart": "2024-01-01",
  "quarterEnd": "2024-03-31",
  "exchangeRate": 80,
  "dataType": "actual"
}
```

### Export Report
```
POST /admin/handlers/quarterly_tax_export.php?report_type=supplier&format=xlsx
Content-Type: application/json

{
  "suppliers": [...],
  "quarter": "Q1",
  "year": 2024,
  "quarterStart": "2024-01-01",
  "quarterEnd": "2024-03-31",
  "exchangeRate": 80
}
```

## UI Ticket Type Badges

| Type | Badge Class | Color | Display |
|------|-------------|-------|---------|
| Booking | `bg-primary` | Blue | "Booking" |
| Refund | `bg-warning` | Yellow | "Refund" |
| Date Change | `bg-info` | Cyan | "Date Change" |

## Table Column Order (9 columns)

1. Issue Date
2. Passenger Name
3. Sector
4. **Type** (colored badge)
5. Status
6. PNR
7. Base Price
8. Sold Price
9. Profit (USD)

## Summary Row Colors

| Row | Background | Text Color |
|-----|-----------|-----------|
| TOTAL (USD) | Light Gray (#D3D3D3) | Black |
| EXCHANGE TO AFN | Yellow (#FFFF99) | Black |
| TAX (4%) | Pink (#FFB6C6) | Black |
| GRAND TOTAL | Dark Gray (#808080) | White |

## Session Variables Required

```php
$_SESSION['user_id']      // Must be set
$_SESSION['role']         // Must be 'admin'
$_SESSION['tenant_id']    // Used for data filtering
$_SESSION['branch_id']    // Used for data filtering
```

## Validation Rules

| Field | Required | Format |
|-------|----------|--------|
| supplier_id | Yes | Integer > 0 |
| quarter | No | Q1, Q2, Q3, Q4 |
| year | Yes | Integer (YYYY) |
| date_from | No | YYYY-MM-DD |
| date_to | No | YYYY-MM-DD |
| exchangeRate | No | Float > 0 |

## Common Errors & Solutions

| Error | Cause | Solution |
|-------|-------|----------|
| "Missing supplier_id" | No suppliers selected | Select at least one supplier |
| "Invalid quarter" | Wrong quarter format | Use Q1, Q2, Q3, or Q4 |
| "Start date must be before end" | Invalid date range | Verify date_from < date_to |
| 403 Unauthorized | Not admin or no session | Login as admin |
| Empty report | No matching data | Check date range and supplier |

## Database Queries (Reference)

### Count bookings by supplier
```sql
SELECT COUNT(*) FROM ticket_bookings 
WHERE tenant_id = ? AND supplier = ?
```

### Count refunds by supplier
```sql
SELECT COUNT(*) FROM refunded_tickets 
WHERE tenant_id = ? AND supplier = ?
```

### Count date changes by supplier
```sql
SELECT COUNT(*) FROM date_change_tickets 
WHERE tenant_id = ? AND supplier = ?
```

## Export File Naming

```
XLSX: supplier_tax_report_Q1_2024.xlsx
PDF:  supplier_tax_report_Q1_2024.pdf
```

## Response Structure (JSON)

### Success Response
```json
{
  "success": true,
  "data": [
    {
      "issue_date": "2024-01-15",
      "full_name": "Mr. John Doe",
      "sector": "NYC - LAX",
      "details": {
        "status": "Booked",
        "pnr": "ABC123",
        "base_price": 100,
        "sold_price": 120,
        "profit": 20,
        "ticket_type": "booking"
      }
    }
  ]
}
```

### Error Response
```json
{
  "success": false,
  "message": "Error description here"
}
```

## Performance Targets

| Operation | Target Time |
|-----------|------------|
| Single supplier, 100 tickets | < 1 sec |
| Single supplier, 500 tickets | 2-3 sec |
| 3 suppliers, 500 each | 3-5 sec |
| XLSX export | Included above |
| PDF export | Included above |

## Sorting Order

Results are sorted by:
1. Primary: `issue_date` (descending - newest first)
2. Within same date: Order of retrieval (bookings, refunds, date_changes)

## Number Format

All monetary values formatted as:
- **Pattern:** #,##0.00
- **Example:** 1,234.56
- **Decimal places:** Always 2
- **Thousands separator:** Comma

## Key Features

✅ Multi-ticket type support (Booking, Refund, Date Change)
✅ Exchange rate conversion
✅ 4% tax extraction
✅ Professional Excel/PDF export
✅ Color-coded visual indicators
✅ Multi-supplier support
✅ Flexible date filtering
✅ Branch isolation
✅ Tenant isolation

## Testing Quick Start

1. Login as admin
2. Go to Admin → Quarterly Tax Report
3. Select a supplier
4. Choose Q1 2024
5. Set exchange rate to 80
6. Click "Generate Report"
7. Review table (should show tickets with Type badges)
8. Click "Export to Excel"
9. Open downloaded file and verify calculations

## Debugging Checklist

- [ ] Check PHP error log for syntax errors
- [ ] Verify database credentials in db.php
- [ ] Confirm tables exist and have data
- [ ] Check session variables are set
- [ ] Verify user is admin role
- [ ] Check tenant_id and branch_id filters
- [ ] Review network tab for API responses
- [ ] Check console for JavaScript errors
- [ ] Verify export directory is writable

## Support Documents

- **QUARTERLY_TAX_REPORT_FINAL.md** - Full feature documentation
- **TESTING_GUIDE.md** - Comprehensive testing procedures
- **DATA_FLOW_REFERENCE.md** - Detailed data flow examples
- **IMPLEMENTATION_COMPLETION_SUMMARY.md** - Implementation overview
- **QUICK_REFERENCE.md** - This file

---

**Last Updated:** April 2024
**Status:** Production Ready
