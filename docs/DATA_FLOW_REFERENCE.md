# Quarterly Tax Report - Data Flow Reference

## End-to-End Data Flow

### 1. User Interaction → Report Generation

```
User opens /admin/quarterly_tax_report.php
    ↓
Selects suppliers (checkboxes)
    ↓
Chooses date range:
  - Quarter (Q1-Q4) + Year, OR
  - Custom date range
    ↓
Enters Exchange Rate (e.g., 80 for USD→AFN)
    ↓
Selects Data Type: "Actual Data"
    ↓
Clicks "Generate Report" button
    ↓
JavaScript function: generateSupplierReport()
```

### 2. Frontend Data Collection

**JavaScript Object Created** (`supplierReportData`):
```javascript
{
  suppliers: [
    { id: 123, name: "Supplier A" },
    { id: 456, name: "Supplier B" }
  ],
  quarter: "Q1",
  year: 2024,
  quarterStart: "2024-01-01",
  quarterEnd: "2024-03-31",
  exchangeRate: 80,
  dataType: "actual"
}
```

### 3. AJAX Request to Backend

**Endpoint:** `admin/handlers/quarterly_tax_handler.php`

**Request:**
```
Method: POST
Content-Type: application/json
Body: supplierReportData object (JSON)
Action: generate_supplier_report
```

### 4. Backend Processing

**Handler receives request:**
```php
$action = 'generate_supplier_report'
$supplier_id = 123 (first in suppliers array)
$data_type = 'actual'
$quarter = 'Q1'
$year = 2024
$branch_id = $_SESSION['branch_id']
$tenant_id = $_SESSION['tenant_id']
```

**Query 1: Regular Bookings**
```sql
SELECT 
    id,
    issue_date,
    CONCAT(title, ' ', passenger_name) as full_name,
    CONCAT(origin, ' - ', destination, ...) as sector,
    status,
    pnr,
    price as base_price,
    sold as sold_price,
    profit,
    description,
    'booking' as ticket_type
FROM ticket_bookings
WHERE tenant_id = 1 
  AND branch_id = 2 
  AND supplier = 123
  AND DATE(issue_date) BETWEEN '2024-01-01' AND '2024-03-31'
```

**Result Example:**
```
id=1, issue_date=2024-01-15, full_name="Mr. John Doe", 
sector="NYC - LAX", status="Booked", pnr="ABC123", 
base_price=100, sold_price=120, profit=20, 
ticket_type='booking'
```

**Query 2: Refunded Tickets**
```sql
SELECT 
    rt.id,
    rt.issue_date,
    CONCAT(rt.title, ' ', rt.passenger_name) as full_name,
    CONCAT(rt.origin, ' - ', rt.destination) as sector,
    rt.status,
    rt.pnr,
    rt.base as base_price,
    rt.sold as sold_price,
    0 as profit,
    rt.remarks as description,
    'refund' as ticket_type
FROM refunded_tickets rt
WHERE rt.tenant_id = 1 
  AND rt.supplier = 123
  AND DATE(rt.created_at) BETWEEN '2024-01-01' AND '2024-03-31'
```

**Result Example:**
```
id=2, issue_date=2024-01-20, full_name="Mrs. Jane Smith", 
sector="NYC - LAX", status="Refunded", pnr="DEF456", 
base_price=100, sold_price=95, profit=0, 
ticket_type='refund'
```

**Query 3: Date Change Tickets**
```sql
SELECT 
    dc.id,
    dc.issue_date,
    CONCAT(dc.title, ' ', dc.passenger_name) as full_name,
    CONCAT(dc.origin, ' - ', dc.destination) as sector,
    dc.status,
    dc.pnr,
    COALESCE(dc.supplier_penalty, 0) as base_price,
    (COALESCE(dc.supplier_penalty, 0) + COALESCE(dc.service_penalty, 0)) as sold_price,
    COALESCE(dc.service_penalty, 0) as profit,
    dc.remarks as description,
    'date_change' as ticket_type
FROM date_change_tickets dc
WHERE dc.tenant_id = 1 
  AND dc.supplier = 123
  AND DATE(dc.created_at) BETWEEN '2024-01-01' AND '2024-03-31'
```

**Result Example:**
```
id=3, issue_date=2024-02-10, full_name="Mr. Bob Johnson", 
sector="NYC - LAX", status="Changed", pnr="GHI789", 
base_price=10, sold_price=15, profit=5, 
ticket_type='date_change'
```

### 5. Data Combination & Formatting

**All results combined:**
```
tickets[] = [
  [Booking row],
  [Refund row],
  [DateChange row]
]
```

**Sorted by issue_date (descending):**
```
tickets[] = [
  [DateChange row - 2024-02-10],
  [Refund row - 2024-01-20],
  [Booking row - 2024-01-15]
]
```

**Formatted for response:**
```php
$formattedData[] = [
    'issue_date' => '2024-02-10',
    'full_name' => 'Mr. Bob Johnson',
    'sector' => 'NYC - LAX',
    'details' => [
        'status' => 'Changed',
        'pnr' => 'GHI789',
        'base_price' => 10.00,
        'sold_price' => 15.00,
        'profit' => 5.00,
        'ticket_type' => 'date_change'  // ← Key identifier
    ]
]
```

### 6. Response to Frontend

**JSON Response:**
```json
{
  "success": true,
  "data": [
    {
      "issue_date": "2024-02-10",
      "full_name": "Mr. Bob Johnson",
      "sector": "NYC - LAX",
      "details": {
        "status": "Changed",
        "pnr": "GHI789",
        "base_price": 10,
        "sold_price": 15,
        "profit": 5,
        "ticket_type": "date_change"
      }
    },
    // ... more tickets
  ]
}
```

### 7. Frontend Display

**Function Called:** `displaySupplierTickets(supplierId, tickets)`

**Table Generation:**
```html
<table class="summary-table">
  <thead>
    <tr>
      <th>Issue Date</th>
      <th>Passenger</th>
      <th>Sector</th>
      <th>Type</th>  <!-- NEW COLUMN -->
      <th>Status</th>
      <th>PNR</th>
      <th>Base Price</th>
      <th>Sold Price</th>
      <th>Profit (USD)</th>
    </tr>
  </thead>
  <tbody>
    <tr>
      <td>2024-02-10</td>
      <td>Mr. Bob Johnson</td>
      <td>NYC - LAX</td>
      <td><span class="badge bg-info">Date Change</span></td>
      <td><span class="badge bg-secondary">Changed</span></td>
      <td><code>GHI789</code></td>
      <td class="text-end">$10.00</td>
      <td class="text-end fw-bold">$15.00</td>
      <td class="text-end text-success fw-bold">$5.00</td>
    </tr>
    <!-- Refund row -->
    <tr>
      <td>2024-01-20</td>
      <td>Mrs. Jane Smith</td>
      <td>NYC - LAX</td>
      <td><span class="badge bg-warning">Refund</span></td>
      <td><span class="badge bg-secondary">Refunded</span></td>
      <td><code>DEF456</code></td>
      <td class="text-end">$100.00</td>
      <td class="text-end fw-bold">$95.00</td>
      <td class="text-end text-success fw-bold">$0.00</td>
    </tr>
    <!-- Booking row -->
    <tr>
      <td>2024-01-15</td>
      <td>Mr. John Doe</td>
      <td>NYC - LAX</td>
      <td><span class="badge bg-primary">Booking</span></td>
      <td><span class="badge bg-secondary">Booked</span></td>
      <td><code>ABC123</code></td>
      <td class="text-end">$100.00</td>
      <td class="text-end fw-bold">$120.00</td>
      <td class="text-end text-success fw-bold">$20.00</td>
    </tr>
  </tbody>
</table>
```

### 8. Calculation Phase (Still in displaySupplierTickets)

**Loop through all tickets:**
```javascript
totalProfit = 0
totalSold = 0

FOR EACH ticket:
  profit = ticket.details.profit
  soldPrice = ticket.details.sold_price
  totalProfit += profit
  totalSold += soldPrice

// Example values
totalProfit = 5 + 0 + 20 = 25
totalSold = 15 + 95 + 120 = 230
exchangeRate = 80

// Exchange rate calculation (applied ONLY to total)
totalExchanged = totalProfit × exchangeRate
totalExchanged = 25 × 80 = 2000 AFN

// Tax calculation
totalTax = totalExchanged × 0.04
totalTax = 2000 × 0.04 = 80 AFN
```

### 9. Summary Rows Display

```html
<!-- TOTAL ROW -->
<tr class="table-light fw-bold">
  <td colspan="6">TOTAL (USD)</td>
  <td class="text-end">-</td>
  <td class="text-end">$230.00</td>
  <td class="text-end text-success">$25.00</td>
</tr>

<!-- EXCHANGE ROW -->
<tr class="table-warning fw-bold">
  <td colspan="6">EXCHANGE TO AFN (@ 80)</td>
  <td class="text-end">-</td>
  <td class="text-end">-</td>
  <td class="text-end text-info">2000.00 AFN</td>
</tr>

<!-- TAX ROW -->
<tr class="table-danger fw-bold">
  <td colspan="6">TAX (4% OF EXCHANGED AMOUNT)</td>
  <td class="text-end">-</td>
  <td class="text-end">-</td>
  <td class="text-end text-danger">80.00 AFN</td>
</tr>
```

### 10. Export Flow

**User clicks "Export to Excel"**

```javascript
serverExport('supplier', 'xlsx')
```

**Request sent to export handler:**
```
POST /admin/handlers/quarterly_tax_export.php?report_type=supplier&format=xlsx
Content-Type: application/json

{
  "suppliers": [
    { "id": 123, "name": "Supplier A" },
    { "id": 456, "name": "Supplier B" }
  ],
  "quarter": "Q1",
  "year": 2024,
  "quarterStart": "2024-01-01",
  "quarterEnd": "2024-03-31",
  "exchangeRate": 80
}
```

### 11. Export Handler Processing

**For EACH supplier:**

```php
$supplierId = 123

// Query 1: Bookings
SELECT ... FROM ticket_bookings 
WHERE tenant_id=1, branch_id=2, supplier=123
AND DATE(issue_date) BETWEEN '2024-01-01' AND '2024-03-31'

// Query 2: Refunds
SELECT ... FROM refunded_tickets 
WHERE tenant_id=1, supplier=123
AND DATE(created_at) BETWEEN '2024-01-01' AND '2024-03-31'

// Query 3: Date Changes
SELECT ... FROM date_change_tickets 
WHERE tenant_id=1, supplier=123
AND DATE(created_at) BETWEEN '2024-01-01' AND '2024-03-31'

// Combine and sort all results
$tickets = array_merge($bookings, $refunds, $dateChanges);
usort($tickets, ...); // Sort by date descending
```

### 12. Excel Workbook Generation

**Using PhpSpreadsheet:**

```
Row 1: "Tax Report - Supplier A"
Row 2: "Period: Q1 2024 (2024-01-01 to 2024-03-31)"
Row 3: [empty]
Row 4: [Headers] Issue Date | Passenger Name | Sector | Type | PNR | Status | Base Price | Sold Price | Profit (USD)
Row 5: [Data row 1 - Date Change]
Row 6: [Data row 2 - Refund]
Row 7: [Data row 3 - Booking]
Row 8: [empty]
Row 9: TOTAL (USD) ... $25.00 (light gray background)
Row 10: EXCHANGE TO AFN @ 80 ... 2000.00 AFN (yellow background)
Row 11: TAX (4% OF EXCHANGED AMOUNT) ... 80.00 AFN (pink background)
Row 12-14: [empty or separator]
Row 15: GRAND TOTAL (USD) ... $X.XX (dark gray)
Row 16: GRAND TOTAL EXCHANGED (AFN) ... X,XXX.XX AFN (dark gray)
Row 17: GRAND TOTAL TAX (AFN) ... X.XX AFN (dark gray)
```

**Formatting Applied:**
```php
// Headers
Font: Bold, Color: White
Fill: #4099FF (blue)
Alignment: Center

// Total rows
Font: Bold
Fill colors: 
  - Light Gray: #D3D3D3
  - Yellow: #FFFF99
  - Pink: #FFB6C6
  - Dark Gray: #808080 (white text)

// Numbers
Format: #,##0.00 (with thousands separator, 2 decimals)
```

### 13. File Output

**For XLSX:**
```
Headers:
  Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet
  Content-Disposition: attachment;filename="supplier_tax_report_Q1_2024.xlsx"
  Cache-Control: max-age=0

Output: Binary XLSX file
```

**For PDF:**
```
Headers:
  Content-Type: application/pdf
  Content-Disposition: attachment;filename="supplier_tax_report_Q1_2024.pdf"

Output: Binary PDF file
```

### 14. Browser Download

```
Browser receives file
↓
Triggers download dialog
↓
User clicks "Save" or "Open"
↓
File saved to downloads folder OR
File opened in Excel/PDF viewer
```

## Profit Mapping Summary

| Ticket Type | Base Price | Sold Price | Profit | Source |
|---|---|---|---|---|
| Booking | `price` | `sold` | `profit` | ticket_bookings |
| Refund | `base` | `sold` | `0` | refunded_tickets |
| Date Change | `supplier_penalty` | `supplier_penalty + service_penalty` | `service_penalty` | date_change_tickets |

## Key Data Points

### Session-Based Filtering
- `tenant_id`: From `$_SESSION['tenant_id']` - Isolates by organization
- `branch_id`: From `$_SESSION['branch_id']` - Isolates by branch

### Date Filtering
- Bookings use: `issue_date`
- Refunds use: `created_at`
- Date Changes use: `created_at`

### Exchange & Tax Calculation
- Applied ONLY to total profit, not individual line items
- Calculation: `Total Profit × Exchange Rate × 0.04`
- Example: $25 profit × 80 AFN rate × 4% = $25 × 80 × 0.04 = 80 AFN

### Type Identification
- Every result row includes: `'ticket_type'` field
- Values: `'booking'`, `'refund'`, `'date_change'`
- Used for badge coloring in UI

## Error Handling Points

**No suppliers selected:**
```
Error: "Please select at least one supplier"
```

**Missing date range:**
```
Error: "Please select a quarter or specify custom date range"
```

**Invalid date range:**
```
Error: "Quarter start date must be before end date"
```

**Database error:**
```
HTTP 500: "Database error: [error message]"
```

**Unauthorized access:**
```
HTTP 403: "Unauthorized"
```

## Performance Optimization

- 3 queries per supplier (not 1 large join)
- In-memory sorting (no database-side sorting overhead)
- Direct field mapping (no complex calculations in SQL)
- Streaming output for export (no file write to disk)

Typical timing:
- Simple supplier (100 tickets): < 1 second
- Complex supplier (1000 tickets): 2-3 seconds
- Multiple suppliers (3 suppliers × 500 tickets): 3-5 seconds
