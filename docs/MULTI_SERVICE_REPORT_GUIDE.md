# Multi-Service Quarterly Tax Report - Implementation Guide

## Overview

The Quarterly Tax Report has been enhanced to support multiple service types beyond tickets:
- **Tickets** (default) - includes bookings, refunds, and date changes
- **Visas** - visa applications only (no refunds)
- **Umrah** - umrah bookings only (no refunds)
- **Hotels** - hotel bookings only (no refunds)
- **All** - combines all four service types

## New Features

### Report Type Selector

A new global selector has been added at the top of both tabs:

```
Select Report Type:
☑ Ticket (default)
  ☐ Visa
  ☐ Umrah
  ☐ Hotel
  ☐ All Types
```

**Default:** Ticket (for backward compatibility)

## Data Mapping by Service Type

### Tickets
**Table:** `ticket_bookings`, `refunded_tickets`, `date_change_tickets`

| Field | Booking | Refund | Date Change |
|-------|---------|--------|-------------|
| Issue Date | `issue_date` | `created_at` | `created_at` |
| Name | `title + passenger_name` | `title + passenger_name` | `title + passenger_name` |
| Sector | `origin - destination` | `origin - destination` | `origin - destination` |
| Base Price | `price` | `base` | `supplier_penalty` |
| Sold Price | `sold` | `sold` | `supplier_penalty + service_penalty` |
| Profit | `profit` | `0` | `service_penalty` |
| Type Badge | "Ticket" (blue) | "Ticket Refund" (yellow) | "Date Change" (cyan) |

### Visa
**Table:** `visa_applications`

| Field | Value |
|-------|-------|
| Issue Date | `receive_date` |
| Name | `applicant_name` |
| Sector | `country - visa_type` |
| Base Price | `base` |
| Sold Price | `sold` |
| Profit | `profit` |
| Type Badge | "Visa" (green) |
| **Note** | No refunds included |

### Umrah
**Tables:** `umrah_bookings` (main) **JOIN** `umrah_booking_services` (services)

| Field | Value |
|-------|-------|
| Issue Date | `umrah_bookings.entry_date` |
| Name | `umrah_bookings.name` |
| Sector | `umrah_bookings.fname - umrah_bookings.relation` |
| Base Price | `umrah_booking_services.base_price` |
| Sold Price | `umrah_booking_services.sold_price` |
| Profit | `umrah_booking_services.profit` |
| Supplier Link | `umrah_booking_services.supplier_id` |
| Type Badge | "Umrah" (red) |
| **Filter** | `status IN ('active', 'pending')` |
| **Note** | Services linked via JOIN, no refunds included |

### Hotel
**Table:** `hotel_bookings`

| Field | Value |
|-------|-------|
| Issue Date | `issue_date` |
| Name | `first_name + last_name` |
| Sector | `accommodation_details` |
| Base Price | `base_amount` |
| Sold Price | `sold_amount` |
| Profit | `profit` |
| Type Badge | "Hotel" (dark gray) |
| **Filter** | `status = 'active'` |
| **Note** | No refunds included |

## Type Badge Colors

| Type | Badge Class | Color |
|------|-------------|-------|
| Ticket | `bg-primary` | Blue |
| Ticket Refund | `bg-warning` | Yellow |
| Date Change | `bg-info` | Cyan |
| Visa | `bg-success` | Green |
| Umrah | `bg-danger` | Red |
| Hotel | `bg-secondary` | Gray |

## Implementation Details

### Backend Changes

#### `quarterly_tax_handler.php`

**New Function:** `fetchTicketsByType()`
- Parameters: `$pdo`, `$tenant_id`, `$branch_id`, `$supplier_id`, `$report_type`, `$from_date`, `$to_date`
- Returns: Combined array of tickets from selected type
- Logic:
  - If type is 'ticket' or 'all': Fetch from all three ticket tables
  - If type is 'visa' or 'all': Fetch from visa_applications
  - If type is 'umrah' or 'all': Fetch from umrah_bookings (filter active/pending)
  - If type is 'hotel' or 'all': Fetch from hotel_bookings (filter active only)

**Modified Function:** `generateSupplierReport()`
- Now receives `report_type` parameter from frontend
- Passes it to `fetchTicketsByType()` for dynamic data fetching

#### `quarterly_tax_export.php`

**New Function:** `fetchTicketsByTypeForExport()`
- Identical logic to handler function but for export processing
- Ensures consistency between display and export

**Modified Function:** `exportSupplierReport()`
- Receives `reportType` from JSON payload
- Uses it for data fetching and label mapping
- Maintains same export formatting

### Frontend Changes

#### `quarterly_tax_report.php`

**New Selector:**
```html
<!-- Report Type Selector (Global for both tabs) -->
<div class="alert alert-info mb-3">
    <label class="mb-0"><strong>Select Report Type:</strong></label>
    <div class="mt-2">
        <div class="form-check form-check-inline">
            <input class="form-check-input" type="radio" name="reportType" 
                   id="type_ticket" value="ticket" checked>
            <label class="form-check-label" for="type_ticket">Ticket</label>
        </div>
        <!-- More options... -->
    </div>
</div>
```

**Updated Display Function:** `displaySupplierTickets()`
- Reads `ticket_type` from response
- Maps types to appropriate badge colors:
  - 'ticket' → Ticket (blue)
  - 'ticket_refund' → Ticket Refund (yellow)
  - 'ticket_date_change' → Date Change (cyan)
  - 'visa' → Visa (green)
  - 'umrah' → Umrah (red)
  - 'hotel' → Hotel (gray)

**Updated Data Payload:**
```javascript
const reportType = document.querySelector('input[name="reportType"]:checked').value;
payload.report_type = reportType;
```

## Query Filtering

### Visa Applications
- **Filter:** `tenant_id`, `supplier`
- **Date Field:** `receive_date`
- **Status:** All statuses included (Applied, Issued, Not Applied)

### Umrah Bookings
- **Filter:** `tenant_id`, `supplier_id` (note: not 'supplier')
- **Date Field:** `entry_date`
- **Status:** Only 'active' and 'pending' included (excludes 'refunded', 'cancelled')

### Hotel Bookings
- **Filter:** `tenant_id`, `supplier_id` (note: not 'supplier')
- **Date Field:** `issue_date`
- **Status:** Only 'active' included (excludes 'refunded')

## Special Considerations

### No Refunds for Non-Ticket Types

Visa, Umrah, and Hotel reports intentionally exclude refund records:
- **Reason:** These services have their own refund logic not integrated into the main booking records
- **Refund Tables Exist:** `visa_refunds`, `umrah_refunds`, `hotel_refunds` but are NOT included
- **Profit Handling:** All records show actual profit from their respective tables

### Status Filtering

Different services use different status filters:

| Service | Status Filter |
|---------|---------------|
| Ticket Bookings | No filter (all statuses) |
| Ticket Refunds | No filter (all refunds included) |
| Date Changes | No filter (all changes included) |
| Visa | No filter (all statuses) |
| Umrah | `status IN ('active', 'pending')` |
| Hotel | `status = 'active'` |

### Profit Calculations

All service types apply the same exchange and tax logic:

```
Total Profit (USD) = Sum of all individual profits
Exchanged Amount = Total Profit × Exchange Rate
Tax = Exchanged Amount × 0.04
```

**Important:** Tax is extracted only from the total, not individual line items.

## Usage Example

### Single Service Type Report

1. Select "Ticket" (or any other type) radio button
2. Choose supplier(s)
3. Select quarter/date range
4. Set exchange rate
5. Click "Generate Report"
6. View table with tickets of selected type only
7. Export to Excel/PDF

### All Services Report

1. Select "All Types" radio button
2. Choose supplier(s)
3. Select quarter/date range
4. Set exchange rate
5. Click "Generate Report"
6. View combined table with all service types, each with distinct badge color
7. Profit total includes all service types
8. Export includes all service types

## Database Column References

For troubleshooting or future extensions, here are the exact column names:

### ticket_bookings
- `id`, `issue_date`, `title`, `passenger_name`, `origin`, `destination`, `trip_type`, `return_origin`, `status`, `pnr`, `price`, `sold`, `profit`, `description`, `tenant_id`, `branch_id`, `supplier`

### refunded_tickets
- `id`, `issue_date`, `title`, `passenger_name`, `origin`, `destination`, `status`, `pnr`, `base`, `sold`, `remarks`, `tenant_id`, `supplier`, `created_at`

### date_change_tickets
- `id`, `issue_date`, `title`, `passenger_name`, `origin`, `destination`, `status`, `pnr`, `supplier_penalty`, `service_penalty`, `remarks`, `tenant_id`, `supplier`, `created_at`

### visa_applications
- `id`, `receive_date`, `applicant_name`, `country`, `visa_type`, `status`, `passport_number`, `base`, `sold`, `profit`, `remarks`, `tenant_id`, `supplier`

### umrah_bookings
- `booking_id`, `entry_date`, `name`, `fname`, `relation`, `status`, `passport_number`, `price`, `sold_price`, `profit`, `remarks`, `tenant_id`, `branch_id`

### umrah_booking_services
- `id`, `booking_id`, `supplier_id`, `base_price`, `sold_price`, `profit`, `service_type`, `currency`, `tenant_id`, `branch_id`

### hotel_bookings
- `id`, `issue_date`, `first_name`, `last_name`, `accommodation_details`, `status`, `order_id`, `base_amount`, `sold_amount`, `profit`, `remarks`, `tenant_id`, `supplier_id`

## Backward Compatibility

✅ **Fully backward compatible:**
- Default report type is "Ticket"
- Existing reports generated without specifying type will use "Ticket"
- All existing functionality preserved
- No breaking changes to API

## Testing Scenarios

### Test 1: Ticket Type Report
- Select Ticket radio button
- Should show only ticket bookings, refunds, and date changes
- Badge colors: blue, yellow, cyan

### Test 2: Visa Type Report
- Select Visa radio button
- Should show only visa applications
- Badge color: green
- No refunds shown

### Test 3: Umrah Type Report
- Select Umrah radio button
- Should show only active/pending umrah bookings
- Badge color: red
- No refunds shown

### Test 4: Hotel Type Report
- Select Hotel radio button
- Should show only active hotel bookings
- Badge color: gray
- No refunds shown

### Test 5: All Types Report
- Select All Types radio button
- Should show combined data from all service types
- Multiple badge colors should appear
- Total profit includes all service types

### Test 6: Export Consistency
- Generate report for any type
- Export to Excel and PDF
- Verify data matches on-screen display exactly
- Verify type labels match between display and export

## Files Modified

1. **admin/quarterly_tax_report.php**
   - Added report type selector UI
   - Updated displaySupplierTickets() for new type badges
   - Updated payload to include report_type

2. **admin/handlers/quarterly_tax_handler.php**
   - Added report_type parameter extraction
   - Added fetchTicketsByType() function
   - Modified generateSupplierReport() to use new function

3. **admin/handlers/quarterly_tax_export.php**
   - Added reportType parameter extraction
   - Added fetchTicketsByTypeForExport() function
   - Updated type label mapping in export

## Future Enhancements

1. **Additional Refund Support**
   - Could add visa_refunds, umrah_refunds, hotel_refunds if business logic changes
   - Would require new type indicators like 'visa_refund', 'umrah_refund', etc.

2. **Service-Specific Filters**
   - Could add visa_status filter (Applied/Issued/Not Applied)
   - Could add umrah duration filter
   - Could add hotel date range filters

3. **Mixed Service Comparisons**
   - Could create custom combinations (e.g., "Tickets and Visas only")
   - Would require UI enhancement for multi-select type selector

4. **Service Performance Analytics**
   - By-service-type profitability charts
   - Service-specific exchange rate handling
   - Service-weighted tax calculations

## Support & Troubleshooting

### Issue: Report shows no data for new types

**Solution:**
1. Verify table exists in database
2. Check supplier_id exists in that table
3. Verify date range contains data
4. Check tenant_id and branch_id filtering

### Issue: Export doesn't match display

**Solution:**
1. Verify both handler and export use same fetchTicketsByType logic
2. Check data source table hasn't changed
3. Verify column name mappings are correct
4. Check timezone/date format consistency

### Issue: Type badges not showing correctly

**Solution:**
1. Verify ticket_type field in response JSON
2. Check JavaScript conditional logic for all type names
3. Verify CSS classes exist (bg-primary, bg-success, etc.)
4. Check browser console for JavaScript errors

