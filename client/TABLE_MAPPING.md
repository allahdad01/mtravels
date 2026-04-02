# Client Dashboard - Database Table Mapping

## Actual Database Tables & Columns

### Tickets
**Table**: `ticket_bookings`
- **Client Reference**: `sold_to` (int)
- **Key Columns**: `id`, `pnr`, `passenger_name`, `origin`, `destination`, `sold`, `created_at`

### Hotels
**Table**: `hotel_bookings`
- **Client Reference**: `sold_to` (varchar) - IMPORTANT: String, not int
- **Key Columns**: `id`, `order_id`, `first_name`, `last_name`, `check_in_date`, `check_out_date`, `sold_amount`, `created_at`

### Visas
**Table**: `visa_applications`
- **Client Reference**: `sold_to` (int)
- **Key Columns**: `id`, `visa_type`, `country`, `applicant_name`, `sold`, `created_at`

### Umrah
**Table**: `umrah_bookings`
- **Client Reference**: `sold_to` (int)
- **Key Columns**: `booking_id`, `fname`, `gfname`, `flight_date`, `sold_price`, `created_at`

### Payments
**Table**: `additional_payments`
- **Client Reference**: `client_id` (int) - DIFFERENT FROM OTHERS
- **Key Columns**: `id`, `amount`, `payment_type`, `currency`, `created_at`

### Client Balance
**Table**: `clients`
- **Client Reference**: `id` (int)
- **Key Columns**: `usd_balance`, `afs_balance`

## Query Patterns

### For Tickets, Visas, Umrah
```sql
WHERE sold_to = ? AND tenant_id = ?
```

### For Hotels
```sql
WHERE CAST(sold_to AS UNSIGNED) = ? AND tenant_id = ?
-- OR
WHERE sold_to = CAST(? AS CHAR) AND tenant_id = ?
```

### For Payments
```sql
WHERE client_id = ? AND tenant_id = ?
```

### For Client
```sql
WHERE id = ? AND tenant_id = ?
```

## Data Type Issues to Watch

1. **hotel_bookings.sold_to** is `VARCHAR(100)` - needs casting when comparing to int
2. **umrah_bookings.booking_id** is primary key, not `id`
3. **umrah_bookings** uses `fname` (first name), not `passenger_name`
4. **visa_applications** uses `country`, not `nationality`
5. **additional_payments** uses `client_id`, not `sold_to`

## Updates Made to Dashboard

All queries in `client/dashboard.php` have been corrected to use:
- Correct table names
- Correct column names
- Correct filter conditions
- Proper column aliases for consistency
