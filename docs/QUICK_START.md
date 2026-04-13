# Quarterly Tax Report Generator - Quick Start Guide

## 🚀 Getting Started

### Access the Report
1. Login to admin panel
2. Navigate to: **Reports** → **Quarterly Tax Report**
3. Two tabs appear: "Individual Supplier Report" and "General Tax Report"

---

## 📋 Individual Supplier Report

### Step 1: Select Date Range

**Option A: Standard Quarter**
```
Year: 2024
Quarter: Click Q1 (Jan 1 - Mar 31)
```

**Option B: Custom Dates**
```
Quarter Start Date: 2024-01-15
Quarter End Date: 2024-04-14
(Custom dates override quarter selection)
```

### Step 2: Select Suppliers
- ☑️ Check boxes next to suppliers
- Each supplier expands to show options

### Step 3: Choose Data Type

**For Actual Data:**
```
Select: "Use Actual Data"
→ System fetches real tickets from database
→ Shows actual prices and commissions
```

**For Random Data:**
```
Select: "Generate Random Data"
Profit Min: 500
Profit Max: 1500
Item Count: 10
→ System generates 10 fictional tickets
→ Each profit between $500-$1500
→ Uses actual ticket templates when available
```

### Step 4: View Report
- Click **Generate Report**
- Table displays with columns:
  - Issue Date
  - Passenger (Title + Name)
  - Sector (Route: Origin - Destination)
  - Status (Booked, Paid, etc.)
  - PNR (Ticket Number)
  - Base Price
  - Sold Price
  - Profit (Commission)

### Step 5: Export
- Click **Export as Excel** → Downloads .xlsx
- Click **Export as PDF** → Downloads .pdf

---

## 💰 General Tax Report

### Step 1: Select Period
- Same as supplier report (quarter or custom dates)

### Step 2: Select Expense Categories
```
Available Categories (left box):
→ Select categories you want

Selected Categories (right box):
→ Appears automatically
→ Shows amount fields
```

### Step 3: Edit Amounts
- Enter amount for each category
- Leave blank for $0
- Use decimal amounts (e.g., 1500.50)

### Step 4: Generate Report
- Click **Generate Report**
- Shows category breakdown with totals

### Step 5: Export
- Click **Export as Excel** or **Export as PDF**

---

## 📊 Report Examples

### Example 1: Q1 2024 Actual Data
```
Quarter: Q1 2024 (Jan 1 - Mar 31)
Data Type: Actual Data
Suppliers: Emirates, Qatar Airways

Result:
├── Emirates
│   ├── 2024-01-15 | Mr. Ahmed Hassan | Cairo - Dubai | Paid | ABC123 | $500 | $650 | $150
│   ├── 2024-02-10 | Mrs. Layla Ahmed | Cairo - Abu Dhabi - Cairo | Paid | XYZ789 | $600 | $800 | $200
│   └── TOTAL: $1,450 | $350
└── Qatar Airways
    ├── 2024-01-20 | Mr. Karim Mansour | Cairo - Doha - Cairo | Booked | QTR456 | $480 | $650 | $170
    └── TOTAL: $650 | $170
```

### Example 2: Random Forecast
```
Quarter: Q2 2024
Data Type: Random Data
Profit Range: 300-1000
Item Count: 5

Result:
├── Emirates
│   ├── 2024-04-05 | Mr. Generated Passenger | Cairo - Dubai | Booked | GEN7A2F1C | $500 | $892 | $392
│   ├── 2024-04-12 | Mr. Generated Passenger | Cairo - Dubai | Paid | GEN5B3E2D | $500 | $701 | $201
│   └── TOTAL: $1,593 | $593
```

### Example 3: Expense Report
```
Period: Q1 2024
Expenses:
├── Salaries: $5,000.00
├── Office Rent: $1,500.00
├── Utilities: $300.00
├── Marketing: $800.00
└── TOTAL: $7,600.00
```

---

## 🎯 Common Tasks

### Task: Generate Monthly Tax Report
```
1. Quarter Start: 2024-02-01
2. Quarter End: 2024-02-29
3. Data Type: Actual Data
4. Select all suppliers
5. Generate Report
6. Export as PDF
```

### Task: Forecast Quarterly Revenue
```
1. Quarter: Q3 (Jul 1 - Sep 30)
2. Data Type: Random Data
3. Profit Min: 500
4. Profit Max: 2000
5. Item Count: 20
6. Generate and review
```

### Task: Calculate Total Expenses
```
1. Select Quarter
2. Select all expense categories
3. Edit amounts as needed
4. Generate Report
5. Review totals
6. Export for accounting
```

### Task: Compare Supplier Performance
```
1. Same date range
2. Data Type: Actual Data
3. Select multiple suppliers
4. Generate
5. Compare profit totals in table
```

---

## 🔍 Understanding the Data

### Column Explanations

| Column | What It Shows | Example |
|--------|---------------|---------|
| Issue Date | When ticket was issued | 2024-01-15 |
| Passenger | Ticket holder name with title | Mr. Ahmed Hassan |
| Sector | Flight route (from - to) | Cairo - Dubai - Cairo |
| Status | Booking status | Paid / Booked / Date Changed |
| PNR | Ticket reference number | ABC123XYZ |
| Base Price | Original ticket price | $500.00 |
| Sold Price | Price you charged customer | $650.00 |
| Profit | Commission you earned | $150.00 |

### Total Row
Shows sums:
- Total Sold Price: Sum of all sold prices
- Total Profit: Sum of all commissions

### Actual vs. Random Data

**Actual Data:**
- Real passenger names from your system
- Real ticket bookings
- Real prices and commissions
- Limited to available data

**Random Data:**
- Generated passenger names or from similar tickets
- Generated profit within your range
- Realistic-looking ticket numbers
- Can generate as many as you need

---

## ⚙️ Settings & Parameters

### Default Values
```
Profit Min (Random): 1,000
Profit Max (Random): 10,000
Item Count (Random): 5
Date Format: YYYY-MM-DD
```

### Quarter Dates
```
Q1: January 1 - March 31
Q2: April 1 - June 30
Q3: July 1 - September 30
Q4: October 1 - December 31
```

### Supported Statuses
```
- Booked (newly created)
- Paid (payment confirmed)
- Date Changed (departure date changed)
- Refunded (ticket refunded)
```

---

## ✅ Validation Rules

**Date Range:**
- Start date must be before end date
- Format: YYYY-MM-DD
- Both required if entering custom dates

**Suppliers:**
- At least one supplier must be selected
- Data type must be selected (Actual or Random)

**Random Data:**
- Profit Min and Max required
- Min must be less than Max
- Item Count must be at least 1

**Expenses:**
- At least one category must be selected
- Amounts must be numeric
- Zero amounts allowed

---

## 🐛 Troubleshooting

### Problem: No Data Showing
**Solution:**
- Check date range has actual tickets
- Verify supplier name matches exactly
- Try different date range
- Confirm tickets exist for that supplier

### Problem: Export Not Working
**Solution:**
- Generate report first before exporting
- Check browser allows file downloads
- Try different export format (Excel vs PDF)
- Check browser console for errors

### Problem: Wrong Totals
**Solution:**
- Verify each item's profit value
- Check base prices are correct
- For random: verify profit range
- Recalculate = Base Price + Profit

### Problem: Date Validation Error
**Solution:**
- Use format: YYYY-MM-DD
- Ensure start date < end date
- Use calendar picker (click input field)

---

## 📱 Tips & Tricks

### Tip 1: Use Standard Quarters
For simplicity, use the Q1-Q4 buttons instead of entering dates manually.

### Tip 2: Generate Multiple Suppliers
Select multiple suppliers and click Generate once. System loads all in one report.

### Tip 3: Export Before Leaving Page
Always export before closing or navigating away to save your report.

### Tip 4: Round Numbers in Random Data
Round numbers to nearest $100 for cleaner reports.

### Tip 5: Check Actual Data First
Always check actual data first to understand real values, then use random for forecasting.

---

## 📞 Need Help?

### Refer to Detailed Docs
- Full Feature Guide: `QUARTERLY_TAX_REPORT.md`
- Flexible Dates Guide: `QUARTERLY_TAX_REPORT_FLEXIBLE_DATES.md`
- Ticket Report Details: `SUPPLIER_REPORT_TICKETS.md`

### Common Issues
- Database connection: Check admin dashboard connectivity
- Missing suppliers: Verify suppliers exist in system and are active
- No expenses: Add expense categories in accounting setup

---

## 🚦 Status Indicators

### Loading
```
⏳ Loading ticket data...
```

### Success
```
✅ Report generated successfully
```

### Error
```
❌ Error loading data: [description]
```

### No Data
```
⚠️ No tickets found for this supplier in the selected period.
```

---

## 📋 Checklist for First Use

- [ ] Login to admin panel
- [ ] Navigate to Reports → Quarterly Tax Report
- [ ] Select a quarter and supplier
- [ ] Click "Generate Report"
- [ ] Review the displayed data
- [ ] Try exporting to Excel
- [ ] Try exporting to PDF
- [ ] Try custom date range
- [ ] Try random data generation
- [ ] Check different suppliers

---

**You're all set!** The Quarterly Tax Report Generator is ready to use. Start with actual data to understand your reports, then use random data for forecasting.
