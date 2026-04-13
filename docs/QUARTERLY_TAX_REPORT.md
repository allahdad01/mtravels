# Quarterly Tax Report Generator

## Overview

The Quarterly Tax Report Generator is an admin-only feature that allows administrators to generate comprehensive tax reports for suppliers and general operations. The system supports both actual data and randomly generated data with customizable parameters.

## Features

### 1. Individual Supplier Report
- **Supplier Selection**: Choose multiple suppliers to include in the report
- **Data Type Options**:
  - **Actual Data**: Uses real transaction data from the database
  - **Random Data**: Generates fictitious data within specified profit ranges
- **Customizable Parameters** (for random data):
  - Profit Range: Set minimum and maximum profit values
  - Item Count: Specify how many items/services to generate
- **Export Formats**: Excel (.xlsx) and PDF (.pdf)

### 2. General Tax Report
- **Quarter & Year Selection**: Choose the specific quarter and year for the report
- **Expense Categories**:
  - Select from available categories
  - Include/exclude specific expenses
  - Edit amounts for each expense
- **Editable Amounts**: All expense amounts are editable for customization
- **Comprehensive Summary**: Total expenses and breakdown by category
- **Export Formats**: Excel (.xlsx) and PDF (.pdf)

## Database Schema

### Tables Created

#### `tax_report_specifications`
Stores configuration for individual supplier tax reports
```sql
- id: INT (Primary Key)
- tenant_id: INT
- supplier_id: INT
- quarter: VARCHAR(3) (Q1, Q2, Q3, Q4)
- year: INT
- data_type: ENUM ('actual', 'random')
- profit_min: DECIMAL (for random data)
- profit_max: DECIMAL (for random data)
- item_count: INT (for random data)
- created_at, updated_at: TIMESTAMP
```

#### `tax_reports`
Stores generated tax reports for audit trail
```sql
- id: INT (Primary Key)
- tenant_id: INT
- quarter: VARCHAR(3)
- year: INT
- report_type: ENUM ('supplier', 'general')
- report_data: LONGTEXT (JSON)
- supplier_id: INT (nullable)
- created_by: INT (nullable)
- created_at, updated_at: TIMESTAMP
```

#### `expense_report_config`
Stores expense configurations for general tax reports
```sql
- id: INT (Primary Key)
- tenant_id: INT
- quarter: VARCHAR(3)
- year: INT
- expense_category: VARCHAR(100)
- included: BOOLEAN
- amount: DECIMAL
- created_at, updated_at: TIMESTAMP
```

#### `supplier_transaction_records`
Stores detailed transaction records
```sql
- id: INT (Primary Key)
- tenant_id: INT
- supplier_id: INT
- quarter: VARCHAR(3)
- year: INT
- transaction_type: VARCHAR(50)
- amount: DECIMAL
- quantity: INT
- description: TEXT
- reference_id: VARCHAR(100)
- created_at: TIMESTAMP
```

## Usage

### Accessing the Page
1. Login as an admin user
2. Navigate to **Reports** → **Quarterly Tax Report** in the sidebar
3. Select either **Individual Supplier Report** or **General Tax Report** tab

### Individual Supplier Report

#### Step 1: Select Quarter & Year
- Choose the year from the dropdown
- Click one of the quarter buttons (Q1, Q2, Q3, Q4)

#### Step 2: Select Suppliers
- Check the boxes next to suppliers to include
- For each selected supplier:
  - Choose **Data Type**:
    - **Actual Data**: Uses real company data from database
    - **Random Data**: Generate fictitious data with custom parameters
  - If using Random Data:
    - Set **Profit Range**: Min and Max profit values
    - Set **Number of Items**: How many items/services to include

#### Step 3: Generate & Export
- Click **Generate Report** to preview
- Review the preview table
- Export as **Excel** or **PDF**

### General Tax Report

#### Step 1: Select Quarter & Year
- Choose the year from the dropdown
- Click one of the quarter buttons (Q1, Q2, Q3, Q4)

#### Step 2: Configure Expenses
- Select categories from "Available Categories" list
- Selected categories appear in "Selected Categories"
- For each selected category:
  - Checkbox indicates if it's included
  - Edit the amount field for customization

#### Step 3: Generate & Export
- Click **Generate Report** to preview
- Review the summary table
- Export as **Excel** or **PDF**

## API Endpoints

### Handler: `/admin/handlers/quarterly_tax_handler.php`

#### Save Supplier Specification
```
POST /admin/handlers/quarterly_tax_handler.php
Action: save_supplier_spec
Parameters:
  - supplier_id: INT
  - quarter: VARCHAR(3)
  - year: INT
  - data_type: ENUM ('actual', 'random')
  - profit_min: DECIMAL (optional)
  - profit_max: DECIMAL (optional)
  - item_count: INT (optional)
```

#### Get Supplier Specification
```
GET /admin/handlers/quarterly_tax_handler.php
Action: get_supplier_spec
Parameters:
  - supplier_id: INT
  - quarter: VARCHAR(3)
  - year: INT
```

#### Get Supplier Data
```
GET /admin/handlers/quarterly_tax_handler.php
Action: get_supplier_data
Parameters:
  - supplier_id: INT
  - quarter: VARCHAR(3)
  - year: INT
```

#### Get Expenses
```
GET /admin/handlers/quarterly_tax_handler.php
Action: get_expenses
Parameters:
  - quarter: VARCHAR(3)
  - year: INT
  - categories: ARRAY (optional)
```

#### Generate Supplier Report
```
POST /admin/handlers/quarterly_tax_handler.php
Action: generate_supplier_report
JSON Body:
  {
    "supplier_id": INT,
    "quarter": "Q1|Q2|Q3|Q4",
    "year": INT,
    "data_type": "actual|random",
    "profit_min": DECIMAL (for random),
    "profit_max": DECIMAL (for random),
    "item_count": INT (for random)
  }
```

#### Generate General Report
```
POST /admin/handlers/quarterly_tax_handler.php
Action: generate_general_report
JSON Body:
  {
    "quarter": "Q1|Q2|Q3|Q4",
    "year": INT,
    "categories": ARRAY,
    "expenses": ARRAY [
      {
        "category": STRING,
        "amount": DECIMAL
      }
    ]
  }
```

## Quarter Mapping

- **Q1**: January, February, March (Months 1-3)
- **Q2**: April, May, June (Months 4-6)
- **Q3**: July, August, September (Months 7-9)
- **Q4**: October, November, December (Months 10-12)

## JavaScript Functions

### Client-Side Functions

#### `setupQuarterButtons()`
Initializes quarter button selection handlers

#### `setupSupplierCheckboxes()`
Initializes supplier checkbox event listeners

#### `setupDataTypeRadios(supplierId)`
Sets up radio button handlers for data type selection

#### `generateSupplierReport()`
Collects supplier report data and displays preview

#### `generateGeneralReport()`
Collects general report data and displays preview

#### `displaySupplierReportPreview()`
Shows preview table for supplier reports

#### `displayGeneralReportPreview()`
Shows preview table for general reports

#### `exportToExcel(data, filename)`
Exports report data to Excel format using XLSX library

#### `exportToPDF(element, filename)`
Exports report preview to PDF using html2pdf library

#### `formatDataForExcel(data)`
Formats data for Excel export

## Required Libraries

### Frontend Dependencies
- **XLSX** (v0.18.5): Excel file generation
  ```html
  <script src="https://cdnjs.cloudflare.com/ajax/libs/xlsx/0.18.5/xlsx.min.js"></script>
  ```
- **html2pdf** (v0.10.1): PDF generation
  ```html
  <script src="https://cdnjs.cloudflare.com/ajax/libs/html2pdf.js/0.10.1/html2pdf.bundle.min.js"></script>
  ```

### PHP Requirements
- PHP 7.4+ with PDO MySQL support
- Session management enabled

## Security Features

1. **Authentication**: Admin-only access
2. **Authorization**: Role-based access control (admin only)
3. **Database**: Tenant isolation with `tenant_id` in all queries
4. **CSRF Protection**: Uses session-based tokens
5. **Input Validation**: Server-side validation of all inputs
6. **SQL Injection Prevention**: Parameterized queries with prepared statements
7. **Error Handling**: Proper HTTP status codes and error messages

## File Structure

```
mtravels/
├── admin/
│   ├── quarterly_tax_report.php          # Main UI page
│   └── handlers/
│       └── quarterly_tax_handler.php     # API endpoint handler
├── migrations/
│   └── 003_create_tax_report_tables.sql  # Database schema
├── includes/
│   └── nav_items.php                     # Navigation menu items
└── docs/
    └── QUARTERLY_TAX_REPORT.md           # This file
```

## Styling

The page uses:
- **Bootstrap 5**: Grid layout and form components
- **Feather Icons**: Icon library for UI elements
- **Custom CSS**: Gradient backgrounds, hover effects, and animations
- **Color Scheme**: Blue (#4099ff) and teal (#2ed8b6) gradient

## Troubleshooting

### Database Connection Issues
- Verify `tenants` table exists
- Check database credentials in `config.php`
- Ensure PDO MySQL driver is installed

### Export Not Working
- Verify XLSX and html2pdf libraries are loaded
- Check browser console for JavaScript errors
- Ensure sufficient memory for large exports

### Data Not Showing
- Verify `tenant_id` is set in session
- Check supplier and expense data exists in database
- Review network tab in browser dev tools for API errors

### Missing Suppliers
- Verify suppliers exist in `supplier_info` table with `status = 'active'`
- Check `tenant_id` matches current user's tenant

## Future Enhancements

1. **Email Integration**: Automatically send reports via email
2. **Scheduled Reports**: Set up automated monthly/quarterly reports
3. **Report History**: Track and compare historical reports
4. **Advanced Filters**: Filter by region, branch, or department
5. **Multi-Supplier Comparison**: Compare metrics across suppliers
6. **Custom Templates**: Create custom report templates
7. **Approval Workflow**: Add approval process for tax reports
8. **Audit Trail**: Detailed logging of all report activities

## Support

For issues or feature requests, contact the development team or create a support ticket in the system.
