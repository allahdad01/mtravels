# Individual Supplier Report - Ticket-Based Detail View

## Overview

The Individual Supplier Report now displays detailed ticket-level information from your booking system. Each ticket shows comprehensive details including passenger information, sector routing, pricing, and commission data.

## Report Display

### Report Header
- **Report Period**: Shows selected quarter range or custom date range
- **Supplier Name**: Identifies the supplier
- **Data Type Badge**: Indicates "Actual Data" or "Random Data"

### Ticket Columns

| Column | Description | Source |
|--------|-------------|--------|
| **Issue Date** | Date ticket was issued | `ticket_bookings.issue_date` |
| **Passenger** | Title + Passenger Name | `ticket_bookings.title + passenger_name` |
| **Sector** | Route information | `origin - destination - return_origin (if round trip)` |
| **Status** | Booking status | `ticket_bookings.status` (Booked, Paid, etc.) |
| **PNR** | Ticket reference number | `ticket_bookings.pnr` |
| **Base Price** | Original ticket price | `ticket_bookings.price` |
| **Sold Price** | Price sold to customer | `ticket_bookings.sold` (actual) or `base_price + profit` (random) |
| **Profit (Commission)** | Commission earned | `ticket_bookings.profit` (actual) or generated (random) |

### Summary Row
- **TOTAL** row showing:
  - Total Sold Price: Sum of all sold prices
  - Total Profit: Sum of all commissions

## Data Types

### 1. Actual Data
**When Selected:** "Use Actual Data"

**What Happens:**
- Fetches real ticket bookings from the database
- Displays exact issue dates
- Shows actual passenger names
- Uses actual routes (origin, destination, return)
- Shows real ticket status
- Displays actual sold prices
- Shows actual profit/commission

**Database Query:**
```sql
SELECT 
  issue_date,
  CONCAT(title, ' ', passenger_name) as full_name,
  CONCAT(origin, ' - ', destination, 
         IF(trip_type='round_trip', ' - ', ''), 
         IF(trip_type='round_trip', return_origin, '')) as sector,
  status,
  pnr,
  price as base_price,
  sold as sold_price,
  profit
FROM ticket_bookings
WHERE tenant_id = ? 
  AND supplier = ?
  AND DATE(issue_date) BETWEEN ? AND ?
ORDER BY issue_date DESC
LIMIT ?
```

**Example Output:**
```
Issue Date: 2024-01-15
Passenger: Mr. Ahmed Hassan
Sector: Cairo - Dubai - Cairo
Status: Paid
PNR: ABC123XYZ
Base Price: $500.00
Sold Price: $650.00
Profit: $150.00
```

### 2. Random Data
**When Selected:** "Generate Random Data" with profit range

**Parameters:**
- **Profit Min**: Minimum profit per ticket (default: 1000)
- **Profit Max**: Maximum profit per ticket (default: 10000)
- **Item Count**: Number of tickets to generate (default: 5)

**What Happens:**
1. Fetches random actual tickets from database as templates
2. Uses actual ticket information (if available)
3. Generates random profit within specified range
4. Calculates new sold price = base price + random profit
5. Fills in actual details where templates exist

**Generation Logic:**
```javascript
For each ticket:
  - randomProfit = random between profitMin and profitMax
  - soldPrice = basePrice + randomProfit
  - If template available: use actual data (date, name, sector)
  - If template not available: generate mock data
```

**Example Output (Random):**
```
Issue Date: 2024-01-10 (actual from similar ticket)
Passenger: Mr. Ahmed Hassan (actual from similar ticket)
Sector: Cairo - Dubai - Cairo (actual from similar ticket)
Status: Booked (actual from similar ticket)
PNR: GEN7A2F1C (generated)
Base Price: $500.00 (actual from similar ticket)
Sold Price: $637.45 (calculated: 500 + 137.45)
Profit: $137.45 (randomly generated within range)
```

## Flexible Date Ranges

### Standard Quarters
Select a year and quarter (Q1, Q2, Q3, Q4):
- **Q1**: January 1 - March 31
- **Q2**: April 1 - June 30
- **Q3**: July 1 - September 30
- **Q4**: October 1 - December 31

### Custom Date Ranges
Specify exact start and end dates:
- Supports fiscal years (e.g., April 1 - June 30)
- Supports mid-month cycles (e.g., Jan 15 - Apr 14)
- Supports project-based periods

### Date Preference
If both quarter and custom dates are provided:
- **Custom dates take precedence**
- Quarter selection is ignored

## Example Scenarios

### Scenario 1: Actual Data for Q1 2024
```
Quarter: Q1
Year: 2024
Data Type: Actual Data

Result:
- Fetches all actual tickets issued Jan 1 - Mar 31, 2024
- Displays real sold prices and commissions
- Limited to available actual data only
```

### Scenario 2: Random Data with Profit Range
```
Data Type: Random Data
Profit Min: 500
Profit Max: 1500
Item Count: 10

Result:
- Generates 10 tickets
- Each ticket profit: random between $500-$1500
- Uses actual ticket templates where available
- Sold Price = Base Price + Random Profit
```

### Scenario 3: Custom Date Range
```
Quarter Start: 2024-02-01
Quarter End: 2024-04-30
Data Type: Actual Data

Result:
- Fetches tickets issued Feb 1 - Apr 30, 2024
- Displays actual data for that period
- Custom dates override any quarter selection
```

## Database Schema Reference

### ticket_bookings Table
Key columns used in report:

```sql
- id: Unique ticket identifier
- tenant_id: Tenant/organization ID
- issue_date: Date ticket was issued
- title: Passenger title (Mr., Mrs., Ms., etc.)
- passenger_name: Passenger full name
- origin: Departure city
- destination: Arrival city
- return_origin: Return departure city (for round trips)
- return_destination: Return arrival city (for round trips)
- trip_type: 'one_way' or 'round_trip'
- pnr: Passenger Name Record (ticket number)
- price: Base ticket price
- sold: Price charged to customer
- profit: Commission earned
- status: 'Booked', 'Paid', 'Date Changed', 'Refunded'
- supplier: Supplier name reference
```

## API Endpoint

### Generate Supplier Report

**URL:** `/admin/handlers/quarterly_tax_handler.php`
**Method:** `POST`
**Content-Type:** `application/json`

**Request Payload:**
```json
{
  "action": "generate_supplier_report",
  "supplier_id": 1,
  "supplier_name": "Emirates Airlines",
  "quarter": "Q1",
  "year": 2024,
  "date_from": null,
  "date_to": null,
  "data_type": "actual",
  "profit_min": 1000,
  "profit_max": 10000,
  "item_count": 5
}
```

**Response (Success):**
```json
{
  "success": true,
  "data": [
    {
      "issue_date": "2024-01-15",
      "full_name": "Mr. Ahmed Hassan",
      "sector": "Cairo - Dubai - Cairo",
      "details": {
        "status": "Paid",
        "pnr": "ABC123XYZ",
        "base_price": 500.00,
        "sold_price": 650.00,
        "profit": 150.00
      }
    }
  ]
}
```

**Response (No Data):**
```json
{
  "success": true,
  "data": []
}
```

## Formatting Rules

### Full Name
```
Format: {title} {passenger_name}
Example: "Mr. Ahmed Hassan"
Example: "Mrs. Layla Ahmed"
```

### Sector
**One-Way Trip:**
```
Format: {origin} - {destination}
Example: "Cairo - Dubai"
```

**Round-Trip:**
```
Format: {origin} - {destination} - {return_origin}
Example: "Cairo - Dubai - Cairo"
```

### Prices
- **Base Price**: Original ticket price from airline
- **Sold Price**: Price you charged customer (actual or calculated)
- **Profit**: Commission earned (actual or generated)

All prices displayed with 2 decimal places and currency symbol ($)

### Status Badge
Status displayed with Bootstrap badge styling:
- **Booked**: Blue badge
- **Paid**: Green badge
- **Date Changed**: Yellow badge
- **Refunded**: Red badge

### PNR
Displayed in monospace font:
- **Actual**: Real PNR from ticket booking
- **Random**: Generated format "GEN" + 6 hex characters (e.g., "GEN7A2F1C")

## Totals Calculation

### Total Sold Price
```
Sum of all (base_price + profit) values
Shown in TOTAL row
```

### Total Profit
```
Sum of all profit values
Shown in TOTAL row
Highlighted in green
```

## Features

✅ **Real Data Display** - See actual ticket bookings and commissions
✅ **Random Generation** - Create test reports with specified profit ranges
✅ **Flexible Dates** - Support for any quarter or custom date range
✅ **Detailed View** - Complete ticket information in tabular format
✅ **Totals Summary** - Quick overview of total sales and commissions
✅ **Professional Styling** - Color-coded status, formatted numbers
✅ **Export Ready** - Tables can be exported to Excel or PDF

## Common Use Cases

### Monthly Tax Compliance Report
```
Quarter Start: 2024-01-01
Quarter End: 2024-01-31
Data Type: Actual Data
Result: All tickets issued in January for tax reporting
```

### Commission Verification
```
Quarter: Q1
Year: 2024
Data Type: Actual Data
Result: Verify all Q1 commissions for supplier payment
```

### Forecast Report
```
Quarter: Q2
Year: 2024
Data Type: Random Data
Profit Min: 500
Profit Max: 2000
Result: Project Q2 commissions with estimated profit ranges
```

### Year-End Review
```
Quarter Start: 2024-01-01
Quarter End: 2024-12-31
Data Type: Actual Data
Result: Full year financial summary for all suppliers
```

## Troubleshooting

### No Data Showing
- Verify ticket bookings exist for the supplier in that date range
- Check supplier name matches exactly in ticket_bookings table
- Try actual data first to confirm tickets exist

### Wrong Prices Showing
- Verify base price in ticket_bookings
- Check sold price was correctly recorded
- Confirm profit calculation (sold - price)

### Missing Passenger Names
- Title field may be empty in database
- Check passenger_name field contains full name
- Some historical records may have incomplete names

### Random Data Shows Same Profit
- Profit range might be too narrow
- Increase profit range (profitMin to profitMax)
- Item count must be within available data

## Performance Notes

- Reports limited to LIMIT clause for performance
- Typical response time: < 1 second for 5-10 items
- Large ranges (year-long) may take 2-3 seconds
- Database indexes on tenant_id, supplier, issue_date recommended

## Future Enhancements

- [ ] Filtering by airline
- [ ] Filtering by ticket status
- [ ] Filtering by passenger name
- [ ] Custom profit ranges per item type
- [ ] Multi-supplier comparison
- [ ] Trend analysis and forecasting
