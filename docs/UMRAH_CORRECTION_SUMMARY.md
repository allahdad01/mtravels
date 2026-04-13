# Umrah Supplier Linking - Correction Summary

## Issue Found

Initial implementation incorrectly assumed umrah supplier was stored in `umrah_bookings` table. After reviewing `api/umrah/add_umrah.php`, the actual structure was discovered.

## Correction Made

### The Real Structure

**Umrah uses a JOIN between two tables:**

1. **`umrah_bookings`** - Main booking record
   - Contains: `booking_id`, `entry_date`, `name`, `fname`, `relation`, `status`, `passport_number`, etc.
   - Does NOT contain supplier information

2. **`umrah_booking_services`** - Service details (linked to bookings)
   - Contains: `booking_id`, `supplier_id`, `base_price`, `sold_price`, `profit`, `service_type`, `currency`
   - **Supplier is linked here via `supplier_id`**

### Updated Query

**Before (Incorrect):**
```sql
FROM umrah_bookings
WHERE tenant_id = ? AND supplier_id = ? AND status IN ('active', 'pending')
```

**After (Correct):**
```sql
FROM umrah_bookings ub
JOIN umrah_booking_services ubs ON ub.booking_id = ubs.booking_id
WHERE ub.tenant_id = ? AND ubs.supplier_id = ? AND ub.status IN ('active', 'pending')
```

## Files Updated

### 1. `admin/handlers/quarterly_tax_handler.php`
- **Function:** `fetchTicketsByType()`
- **Change:** Updated umrah query to use JOIN with `umrah_booking_services`
- **Line Range:** ~566-595

### 2. `admin/handlers/quarterly_tax_export.php`
- **Function:** `fetchTicketsByTypeForExport()`
- **Change:** Updated umrah query to use JOIN with `umrah_booking_services`
- **Line Range:** ~440-468

## Query Details

### Correct Umrah Query Structure

```php
$query = "SELECT 
    ub.booking_id as id,
    ub.entry_date as issue_date,
    ub.name as full_name,
    CONCAT(ub.fname, ' - ', ub.relation) as sector,
    ub.status,
    ub.passport_number as pnr,
    ubs.base_price,              // ← From services table
    ubs.sold_price,              // ← From services table
    ubs.profit,                  // ← From services table
    ub.remarks as description,
    'umrah' as ticket_type
FROM umrah_bookings ub
JOIN umrah_booking_services ubs ON ub.booking_id = ubs.booking_id
WHERE ub.tenant_id = ? AND ubs.supplier_id = ? AND ub.status IN ('active', 'pending')";
```

## Why This Structure Exists

Looking at `add_umrah.php`:
- Line 114-127: Insert into `umrah_bookings` (main booking record)
- Line 169-187: Insert into `umrah_booking_services` (service details including supplier)

This allows:
- One booking with multiple services
- Each service has its own supplier, price, and profit
- Flexibility in service provider management

## Comparison with Other Services

| Service | Structure |
|---------|-----------|
| **Ticket** | Direct columns in `ticket_bookings` |
| **Visa** | Direct columns in `visa_applications` |
| **Umrah** | JOIN: `umrah_bookings` + `umrah_booking_services` |
| **Hotel** | Direct columns in `hotel_bookings` |

## Data Mapping Clarification

For Umrah reports, data is now correctly fetched from:

| Field | Table |
|-------|-------|
| Date, Name, Sector, Status, PNR | `umrah_bookings` |
| Base Price, Sold Price, Profit | `umrah_booking_services` |
| Supplier Filter | `umrah_booking_services.supplier_id` |

## Validation

✅ Both modified files pass PHP syntax validation
✅ No breaking changes to existing functionality
✅ Backward compatible with default "Ticket" type
✅ Consistent with API implementation

## Testing

When testing Umrah reports:

1. **Verify Join Works:**
   - Select "Umrah" report type
   - Choose a supplier that has umrah bookings
   - Should display umrah data correctly

2. **Verify Data Accuracy:**
   - Check that profit values match `umrah_booking_services.profit`
   - Verify multiple services per booking show as separate rows if needed
   - Confirm supplier filtering works correctly

3. **Verify Export:**
   - Generate Umrah report
   - Export to Excel/PDF
   - Verify data matches on-screen display

## Impact on Other Types

✅ **No impact on:**
- Ticket reports (unchanged)
- Visa reports (unchanged)
- Hotel reports (unchanged)
- All types combined reports (now correctly includes umrah)

## Future Considerations

If umrah structure changes:
- Update JOIN condition if key fields change
- Update field references in SELECT list
- Update WHERE clause if supplier link changes
- Update date filtering if date field changes

---

**Status:** ✅ Corrected and Validated

The Umrah report type now correctly fetches supplier-linked data from the `umrah_booking_services` table.
