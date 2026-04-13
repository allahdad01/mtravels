# How Ticket Data Fetching Works - Complete Explanation

## Overview

When you generate a supplier report with **"Actual Data"**, the system fetches real ticket bookings from your database. Here's exactly how it works:

---

## Step-by-Step Data Flow

### Step 1: Frontend Sends Request
You click **"Generate Report"** and the system sends:

```javascript
{
  "action": "generate_supplier_report",
  "supplier_id": "60",
  "supplier_name": "KamAir",         // ← KEY: Supplier NAME not ID
  "data_type": "actual",
  "date_from": "2026-02-13",
  "date_to": "2026-04-13",
  "quarter": "Q1"
}
```

**Important:** It sends the supplier **NAME** (e.g., "KamAir"), not the ID.

### Step 2: Backend Receives Request
The handler receives the JSON and extracts:
```php
$supplier_name = $data['supplier_name'];  // "KamAir"
$data_type = $data['data_type'];          // "actual"
$tenant_id = $_SESSION['tenant_id'];      // Your org ID
$from_date = "2026-02-13";
$to_date = "2026-04-13";
```

### Step 3: Build SQL Query
The system builds a query:

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
  profit
FROM ticket_bookings
WHERE tenant_id = ?          -- Your organization
  AND supplier = ?           -- Match supplier name
  AND DATE(issue_date) BETWEEN ? AND ?  -- Within date range
ORDER BY issue_date DESC
LIMIT 5                      -- Max 5 tickets
```

### Step 4: Execute Query
```php
// Bind parameters securely
$params = [$tenant_id, $supplier_name, $from_date, $to_date, $item_count];

$stmt = $pdo->prepare($query);
$stmt->execute($params);
$tickets = $stmt->fetchAll(PDO::FETCH_ASSOC);
```

### Step 5: Format Results
For each ticket found, create formatted data:

```php
[
  "issue_date" => "2026-02-15",
  "full_name" => "Mr. Ahmed Hassan",
  "sector" => "Cairo - Dubai - Cairo",
  "details" => [
    "status" => "Paid",
    "pnr" => "ABC123XYZ",
    "base_price" => 500.00,
    "sold_price" => 650.00,
    "profit" => 150.00
  ]
]
```

### Step 6: Return Response
```json
{
  "success": true,
  "data": [
    { ... ticket 1 ... },
    { ... ticket 2 ... },
    { ... ticket 3 ... }
  ]
}
```

### Step 7: Display Results
Frontend renders the table with the ticket data.

---

## Why "No tickets found" Message?

This message appears when the query returns **zero rows**. This happens when:

### Reason 1: Supplier Name Doesn't Match
**Example Problem:**
- You select supplier: **"KamAir"**
- Database has tickets with supplier: **"KAM Air"** (space in name)
- Result: NO MATCH ❌

**Database Value:**
```sql
SELECT DISTINCT supplier FROM ticket_bookings
WHERE tenant_id = 1
LIMIT 10;

Output:
KamAir
Kam Air           ← Different!
KAMIR
Emirates
Qatar Airways
```

### Reason 2: No Tickets in Date Range
**Example Problem:**
- You select: Feb 13, 2026 - Apr 13, 2026
- Supplier tickets exist, but all issued:
  - BEFORE Feb 13 (Jan 2026)
  - AFTER Apr 13 (May 2026)
- Result: NO MATCH ❌

**Database Data:**
```sql
SELECT issue_date FROM ticket_bookings 
WHERE supplier = 'KamAir';

Output:
2026-01-15  ← Before range
2026-01-20  ← Before range
2026-05-10  ← After range
2026-05-15  ← After range
```

### Reason 3: No Tickets for Supplier
**Example Problem:**
- You select supplier: **"AeroSvit"**
- Database has NO tickets for this supplier
- Result: NO MATCH ❌

**Database State:**
```sql
SELECT COUNT(*) FROM ticket_bookings 
WHERE supplier = 'AeroSvit';

Output: 0 rows
```

### Reason 4: Supplier Name Case Mismatch
**Example Problem:**
- You select: **"kamair"** (lowercase)
- Database has: **"KamAir"** (mixed case)
- SQL might be case-sensitive
- Result: NO MATCH ❌

---

## How to Debug "No Tickets Found"

### Debug Step 1: Check Actual Supplier Names
Run this query directly in your database:

```sql
SELECT DISTINCT supplier 
FROM ticket_bookings 
WHERE tenant_id = 1 
ORDER BY supplier;
```

**This shows:** All unique supplier names in your database

Compare with the supplier name you selected in the form. It must match EXACTLY.

### Debug Step 2: Check Tickets for That Supplier
Use the exact supplier name from your database:

```sql
SELECT COUNT(*) as ticket_count
FROM ticket_bookings 
WHERE tenant_id = 1 
  AND supplier = 'KamAir';  -- Use exact name
```

**This shows:** How many tickets exist for that supplier (total)

### Debug Step 3: Check Tickets in Your Date Range
```sql
SELECT COUNT(*) as ticket_count
FROM ticket_bookings 
WHERE tenant_id = 1 
  AND supplier = 'KamAir'
  AND DATE(issue_date) BETWEEN '2026-02-13' AND '2026-04-13';
```

**This shows:** How many tickets exist in that date range

### Debug Step 4: See Actual Tickets
If count > 0, see the actual tickets:

```sql
SELECT 
  issue_date,
  title,
  passenger_name,
  origin,
  destination,
  price,
  sold,
  profit
FROM ticket_bookings 
WHERE tenant_id = 1 
  AND supplier = 'KamAir'
  AND DATE(issue_date) BETWEEN '2026-02-13' AND '2026-04-13'
ORDER BY issue_date DESC;
```

**This shows:** The actual ticket data that should be displayed

---

## Visual Data Flow Diagram

```
┌─────────────────────────────────────────────────────────┐
│ FRONTEND: User Selection                                │
├─────────────────────────────────────────────────────────┤
│ Supplier: KamAir                                        │
│ Data Type: Actual Data                                  │
│ Period: Feb 13 - Apr 13, 2026                           │
└────────────────┬────────────────────────────────────────┘
                 │
                 ▼
┌─────────────────────────────────────────────────────────┐
│ AJAX REQUEST: Send JSON payload                         │
├─────────────────────────────────────────────────────────┤
│ {                                                       │
│   "action": "generate_supplier_report",                 │
│   "supplier_name": "KamAir",                            │
│   "data_type": "actual",                                │
│   "date_from": "2026-02-13",                            │
│   "date_to": "2026-04-13"                               │
│ }                                                       │
└────────────────┬────────────────────────────────────────┘
                 │
                 ▼
┌─────────────────────────────────────────────────────────┐
│ BACKEND: Handler receives request                       │
├─────────────────────────────────────────────────────────┤
│ Parse JSON                                              │
│ Extract: supplier_name = "KamAir"                       │
│ Extract: tenant_id = 1                                  │
│ Extract: date range                                     │
└────────────────┬────────────────────────────────────────┘
                 │
                 ▼
┌─────────────────────────────────────────────────────────┐
│ DATABASE QUERY                                          │
├─────────────────────────────────────────────────────────┤
│ SELECT * FROM ticket_bookings                           │
│ WHERE                                                   │
│   tenant_id = 1                                         │
│   AND supplier = 'KamAir'                               │
│   AND issue_date BETWEEN '2026-02-13' AND '2026-04-13' │
│                                                         │
│ Result: 3 tickets found ✅                              │
│ (or 0 tickets found ❌)                                  │
└────────────────┬────────────────────────────────────────┘
                 │
                 ▼
┌─────────────────────────────────────────────────────────┐
│ FORMAT DATA                                             │
├─────────────────────────────────────────────────────────┤
│ For each ticket:                                        │
│  - Extract issue_date                                   │
│  - CONCAT title + passenger_name                        │
│  - CONCAT origin + destination                          │
│  - Extract status, pnr, price, sold, profit            │
│  - Create formatted object                              │
└────────────────┬────────────────────────────────────────┘
                 │
                 ▼
┌─────────────────────────────────────────────────────────┐
│ SEND RESPONSE                                           │
├─────────────────────────────────────────────────────────┤
│ {                                                       │
│   "success": true,                                      │
│   "data": [                                             │
│     {                                                   │
│       "issue_date": "2026-02-15",                       │
│       "full_name": "Mr. Ahmed",                         │
│       "sector": "Cairo - Dubai",                        │
│       "details": {...}                                  │
│     }                                                   │
│   ]                                                     │
│ }                                                       │
└────────────────┬────────────────────────────────────────┘
                 │
                 ▼
┌─────────────────────────────────────────────────────────┐
│ FRONTEND: Display Results                               │
├─────────────────────────────────────────────────────────┤
│ If data.length > 0:                                     │
│   Render table with tickets                             │
│ Else:                                                   │
│   Show "No tickets found" message                       │
└─────────────────────────────────────────────────────────┘
```

---

## The SQL WHERE Conditions Explained

### Condition 1: `tenant_id = ?`
```sql
WHERE tenant_id = 1
```
**Purpose:** Isolate data to your organization only
**Value:** Your logged-in tenant ID from `$_SESSION['tenant_id']`
**Example:** Only show tickets for your company, not other companies

### Condition 2: `supplier = ?`
```sql
AND supplier = 'KamAir'
```
**Purpose:** Filter tickets from specific supplier
**Value:** The supplier name you selected
**Critical:** Must match EXACTLY (including spaces, case)
**Common Issue:** "KamAir" ≠ "KAM AIR" ≠ "kamair"

### Condition 3: `DATE(issue_date) BETWEEN ? AND ?`
```sql
AND DATE(issue_date) BETWEEN '2026-02-13' AND '2026-04-13'
```
**Purpose:** Only include tickets issued in date range
**Start:** Your "Quarter Start Date" or Q1 start
**End:** Your "Quarter End Date" or Q1 end
**Format:** YYYY-MM-DD

### Condition 4: `LIMIT ?`
```sql
LIMIT 5
```
**Purpose:** Maximum 5 tickets returned
**Value:** Your "Item Count" selection (default 5)
**Note:** If more than 5 exist, only newest 5 shown (ORDER BY issue_date DESC)

---

## Example Scenarios

### Scenario 1: Everything Matches ✅
```
Database has:
- Ticket 1: supplier='KamAir', issue_date='2026-02-15'
- Ticket 2: supplier='KamAir', issue_date='2026-03-10'
- Ticket 3: supplier='KamAir', issue_date='2026-04-05'

Query:
WHERE supplier = 'KamAir' 
  AND DATE(issue_date) BETWEEN '2026-02-13' AND '2026-04-13'

Result: ✅ All 3 tickets found
```

### Scenario 2: Supplier Name Wrong ❌
```
Database has:
- Ticket 1: supplier='Kam Air' (space), issue_date='2026-02-15'

You select: 'KamAir' (no space)

Query:
WHERE supplier = 'KamAir' 

Result: ❌ No match (because 'KamAir' ≠ 'Kam Air')
```

### Scenario 3: Date Range Wrong ❌
```
Database has:
- Ticket 1: supplier='KamAir', issue_date='2026-01-10'
- Ticket 2: supplier='KamAir', issue_date='2026-05-20'

You select: 2026-02-13 to 2026-04-13

Query:
WHERE DATE(issue_date) BETWEEN '2026-02-13' AND '2026-04-13'

Result: ❌ No match (tickets before/after range)
```

---

## Quick Troubleshooting Checklist

- [ ] **Supplier name matches exactly?**
  - Check database for exact name (spaces, case)
  - Use Debug Step 1 query above

- [ ] **Tickets exist for supplier?**
  - Use Debug Step 2 query above
  - Should show count > 0

- [ ] **Tickets in date range?**
  - Use Debug Step 3 query above
  - Check if tickets exist in selected dates

- [ ] **Correct tenant ID?**
  - Your tickets belong to your organization
  - System filters by tenant_id automatically

- [ ] **Date format correct?**
  - Must be YYYY-MM-DD (e.g., 2026-02-13)
  - Not MM/DD/YYYY or other formats

---

## Summary

**Data Fetching Process:**
1. Frontend sends supplier name + date range
2. Backend builds SQL query with filters
3. Database searches for matching tickets
4. Results formatted and returned
5. Frontend displays in table

**If "No tickets found":**
1. Supplier name doesn't match (most common)
2. No tickets in that date range
3. No tickets for that supplier
4. Tenant ID mismatch (rare)

**How to Fix:**
1. Run Debug queries to find real supplier name
2. Check if tickets exist in database
3. Verify date range has data
4. Try different dates or supplier name
