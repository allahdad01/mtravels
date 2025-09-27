# Tutorial Screenshots Setup Guide

This guide explains how to add screenshots to the tutorials system for the Almoqadas Management System.

## Screenshot Placeholders

Throughout the tutorial content, you'll find screenshot placeholders that look like this:

```html
<div class="screenshot-placeholder mt-2">
    <i class="fas fa-image me-1"></i> Screenshot: Description of what should be shown
</div>
```

## How to Add Screenshots

### Step 1: Prepare Your Screenshots

1. **Take Screenshots**: Capture clear, high-quality screenshots of each step in the process
2. **Recommended Size**: 1200px width (height can vary)
3. **Format**: Use PNG or JPG format
4. **Naming Convention**: Use descriptive names like:
   - `ticket-booking-step-1-navigation.png`
   - `ticket-booking-step-2-book-button.png`
   - `transaction-modal-opened.png`
   - `payment-form-filled.png`

### Step 2: Create Screenshots Directory

Create a directory structure for storing screenshots:

```
admin/
├── assets/
│   └── images/
│       └── tutorials/
│           ├── ticket-booking/
│           ├── transaction-management/
│           ├── visa-management/
│           ├── umrah-management/
│           ├── financial-management/
│           └── reports-system/
```

### Step 3: Upload Screenshots

1. Place your screenshots in the appropriate subdirectory
2. Use consistent naming that matches the tutorial steps
3. Optimize images for web (compress without losing clarity)

### Step 4: Replace Placeholders

Replace each placeholder with an actual image tag:

**Before:**
```html
<div class="screenshot-placeholder mt-2">
    <i class="fas fa-image me-1"></i> Screenshot: Ticket management page with "Book Ticket" button
</div>
```

**After:**
```html
<div class="tutorial-screenshot mt-2">
    <img src="assets/images/tutorials/ticket-booking/step-1-navigation.png" 
         alt="Ticket management page with Book Ticket button" 
         class="img-fluid rounded shadow-sm"
         onclick="openImageModal(this)">
    <p class="screenshot-caption text-muted mt-1 small">
        <i class="fas fa-info-circle me-1"></i>
        Ticket management page with "Book Ticket" button highlighted
    </p>
</div>
```

### Step 5: Add CSS for Screenshots

Add this CSS to `admin/tutorials.php` within the `<style>` section:

```css
.tutorial-screenshot {
    margin: 15px 0;
    text-align: center;
}

.tutorial-screenshot img {
    max-width: 100%;
    height: auto;
    cursor: pointer;
    transition: transform 0.2s ease;
}

.tutorial-screenshot img:hover {
    transform: scale(1.02);
}

.screenshot-caption {
    font-size: 0.9rem;
    font-style: italic;
    margin-top: 5px;
}

/* Image Modal for Full View */
.image-modal {
    display: none;
    position: fixed;
    z-index: 9999;
    left: 0;
    top: 0;
    width: 100%;
    height: 100%;
    background-color: rgba(0,0,0,0.8);
    cursor: pointer;
}

.image-modal img {
    position: absolute;
    top: 50%;
    left: 50%;
    transform: translate(-50%, -50%);
    max-width: 90%;
    max-height: 90%;
    border-radius: 8px;
}

.image-modal .close-modal {
    position: absolute;
    top: 20px;
    right: 30px;
    color: white;
    font-size: 40px;
    font-weight: bold;
    cursor: pointer;
}
```

### Step 6: Add JavaScript for Image Modal

Add this JavaScript to `admin/tutorials.php` before the closing `</body>` tag:

```javascript
// Image Modal Functionality
function openImageModal(img) {
    const modal = document.createElement('div');
    modal.className = 'image-modal';
    modal.innerHTML = `
        <span class="close-modal">&times;</span>
        <img src="${img.src}" alt="${img.alt}">
    `;
    
    document.body.appendChild(modal);
    modal.style.display = 'block';
    
    modal.onclick = function() {
        document.body.removeChild(modal);
    };
}
```

## Screenshots Needed

### Ticket Booking Process (14 screenshots):

1. **Step 1**: Ticket management page with "Book Ticket" button
2. **Step 2**: Clicking the "Book Ticket" button
3. **Step 3**: Booking Details section filled out
4. **Step 4**: Passenger Information section with details filled
5. **Step 5**: Flight Details section completed
6. **Step 6**: Payment Information section filled
7. **Step 7**: Success message after booking
8. **Step 8**: New ticket appearing in the ticket list
9. **Step 9**: Actions dropdown menu with "Manage Transactions" option
10. **Step 10**: Transaction management modal opened
11. **Step 11**: Clicking "New Transaction" button
12. **Step 12**: Transaction form filled with payment details
13. **Step 13**: Transaction saved successfully
14. **Step 14**: Updated payment status and transaction history

### Transaction Management Process (12 screenshots):

1. **Step 1**: Ticket list with search functionality
2. **Step 2**: Clicking the actions dropdown menu
3. **Step 3**: Dropdown menu with "Manage Transactions" highlighted
4. **Step 4**: Transaction modal showing ticket information
5. **Step 5**: Transaction history table
6. **Step 6**: Clicking "New Transaction" button
7. **Step 7**: Transaction form filled with payment details
8. **Step 8**: Success message after saving transaction
9. **Step 9**: Updated payment status and new transaction in history
10. **Step 10**: Editing an existing transaction
11. **Step 11**: Deleting a transaction with confirmation dialog
12. **Step 12**: Updated ticket list showing new payment status

### Ticket Refund Process (14 screenshots):

1. **Step 1**: Refund tickets page with statistics and "Add Refund Ticket" button
2. **Step 2**: Clicking "Add Refund Ticket" button
3. **Step 3**: Search section with PNR and passenger name fields
4. **Step 4**: Search results table with selectable tickets
5. **Step 5**: Refund form showing original ticket prices
6. **Step 6**: Penalty fields with supplier and service penalty amounts
7. **Step 7**: Calculation method dropdown with automatic refund calculation
8. **Step 8**: Description textarea with sample refund notes
9. **Step 9**: Success message after saving refund ticket
10. **Step 10**: New refunded ticket in the main list
11. **Step 11**: Actions dropdown with "Manage Payments" option
12. **Step 12**: Edit penalties modal with updated amounts
13. **Step 13**: Generated refund agreement document
14. **Step 14**: Payment status indicators in the refund tickets table

### Date Change Process (15 screenshots):

1. **Step 1**: Date change management page with statistics and "Add Date Change" button
2. **Step 2**: Clicking "Add Date Change" button
3. **Step 3**: Search section with PNR and passenger name fields
4. **Step 4**: Search results table with selectable tickets
5. **Step 5**: New departure date field with date picker
6. **Step 6**: Exchange rate field with current rate
7. **Step 7**: Supplier and service penalty fields
8. **Step 8**: Base price and sold price fields
9. **Step 9**: Description textarea with sample change notes
10. **Step 10**: Success message after saving date change
11. **Step 11**: New date change record in the main list
12. **Step 12**: Manage Transactions button in actions
13. **Step 13**: Transaction management modal with payment form
14. **Step 14**: Print agreement button and generated document
15. **Step 15**: Payment status indicators in the date change table

### Ticket Weight Management Process (15 screenshots):

1. **Step 1**: Ticket weights management page with "Add Weight" button
2. **Step 2**: Clicking "Add Weight" button
3. **Step 3**: Search section with PNR and passenger name fields
4. **Step 4**: Search results table with selectable tickets
5. **Step 5**: Weight field with kilogram input
6. **Step 6**: Base price and sold price fields with profit calculation
7. **Step 7**: Profit field showing calculated margin
8. **Step 8**: Remarks textarea with sample weight notes
9. **Step 9**: Success message after saving weight record
10. **Step 10**: New weight record in the main list
11. **Step 11**: Manage Transactions button in actions
12. **Step 12**: Transaction management modal with weight payment form
13. **Step 13**: Edit weight modal with updated values
14. **Step 14**: Payment status indicators in the weight table
15. **Step 15**: Delete confirmation dialog

### Ticket Reservation Process (15 screenshots):

1. **Step 1**: Ticket reservations page with search bar and "Reserve Ticket" button
2. **Step 2**: Clicking the "Reserve Ticket" button
3. **Step 3**: Supplier dropdown with search functionality
4. **Step 4**: Client selection dropdown with active clients
5. **Step 5**: Trip type dropdown selection
6. **Step 6**: Passenger details form fields filled
7. **Step 7**: Flight details section with origin, destination, and airline
8. **Step 8**: Date fields with date pickers
9. **Step 9**: Pricing fields with base, sold, and calculated profit
10. **Step 10**: Exchange rate fields
11. **Step 11**: Main account selection dropdown
12. **Step 12**: Description field with sample text
13. **Step 13**: Completed form with "Book" button highlighted
14. **Step 14**: Updated reservation list showing new ticket
15. **Step 15**: Actions dropdown with "Manage Transactions" option

### Manage Reservation Transactions Process (12 screenshots):

1. **Step 1**: Actions dropdown with "Manage Transactions" highlighted
2. **Step 2**: Transaction modal header with reservation details
3. **Step 3**: Payment status summary with amounts by currency
4. **Step 4**: Transaction history table with existing payments
5. **Step 5**: "New Transaction" button highlighted
6. **Step 6**: Date and time fields filled
7. **Step 7**: Payment amount field with value
8. **Step 8**: Currency dropdown selection
9. **Step 9**: Payment description textarea with sample text
10. **Step 10**: "Add Transaction" button highlighted
11. **Step 11**: Updated transaction history with new payment
12. **Step 12**: Reservations list showing updated payment status indicator

### Hotel Booking Creation Process (12 screenshots):

1. **Step 1**: Hotel bookings page with search functionality and "New Booking" button
2. **Step 2**: Clicking the "New Booking" button
3. **Step 3**: Guest Information section with all fields filled
4. **Step 4**: Booking Details section with order ID, issue date, and contact number
5. **Step 5**: Stay Details section with dates and accommodation description
6. **Step 6**: Financial Details section with base amount, sold amount, and calculated profit
7. **Step 7**: Additional Details section with supplier, client, and account dropdowns
8. **Step 8**: Exchange rate and currency selection fields
9. **Step 9**: Remarks textarea with sample booking notes
10. **Step 10**: Completed form with "Add Booking" button highlighted
11. **Step 11**: Updated hotel bookings list showing new reservation
12. **Step 12**: Booking action buttons (view, edit, transactions, more options)

### Hotel Transaction Management Process (12 screenshots):

1. **Step 1**: Transaction management button highlighted in booking actions
2. **Step 2**: Transaction modal header with booking summary card
3. **Step 3**: Payment status summary with amounts by currency
4. **Step 4**: Transaction history table with existing payments
5. **Step 5**: "Add Transaction" button highlighted
6. **Step 6**: Date and time fields in transaction form
7. **Step 7**: Payment amount field with currency symbol
8. **Step 8**: Currency dropdown with options
9. **Step 9**: Payment description textarea with sample text
10. **Step 10**: "Add Transaction" button in form footer
11. **Step 11**: Updated transaction history with new payment entry
12. **Step 12**: Transaction actions (edit/delete) in history table

### Hotel Booking Refund Process (9 screenshots):

1. **Step 1**: Dropdown menu with "Process Refund" option highlighted
2. **Step 2**: Refund modal with booking summary showing original amounts
3. **Step 3**: Exchange rate field with current rate displayed
4. **Step 4**: Refund type selection buttons (Full/Partial)
5. **Step 5**: Refund amount field with maximum amount shown
6. **Step 6**: Reason for refund textarea with sample text
7. **Step 7**: Completed refund form with "Process Refund" button
8. **Step 8**: Success message and updated booking status
9. **Step 9**: Hotel refunds page showing processed refund

### Umrah Family Management Process (7 screenshots):

1. **Step 1**: Umrah management main page
2. **Step 2**: "Add New Family" button
3. **Step 3**: Family head information form
4. **Step 4**: Package type selection
5. **Step 5**: Visa and tazmin status selection
6. **Step 6**: Family creation confirmation
7. **Step 7**: Family management action buttons

### Umrah Booking Management Process (9 screenshots):

1. **Step 1**: Family group selection
2. **Step 2**: "Add New Member" button
3. **Step 3**: Business partner selection
4. **Step 4**: Personal information form
5. **Step 5**: Travel details form
6. **Step 6**: Financial information form
7. **Step 7**: Payment information form
8. **Step 8**: Passport scanning process
9. **Step 9**: Booking confirmation

### Umrah Transaction Management Process (8 screenshots):

1. **Step 1**: Transaction management button
2. **Step 2**: Booking financial summary
3. **Step 3**: Transaction history table
4. **Step 4**: "New Transaction" button
5. **Step 5**: Transaction details form
6. **Step 6**: Bank transaction details
7. **Step 7**: Transaction confirmation
8. **Step 8**: Transaction edit/delete actions

### Umrah Refund Management Process (9 screenshots):

1. **Step 1**: Refund option in booking actions
2. **Step 2**: Booking financial summary
3. **Step 3**: Exchange rate input
4. **Step 4**: Refund type selection
5. **Step 5**: Partial refund amount input
6. **Step 6**: Refund reason textarea
7. **Step 7**: "Process Refund" button
8. **Step 8**: Refund confirmation
9. **Step 9**: Refund document generation

### Umrah Document Generation Process (7 screenshots):

1. **Step 1**: Document type selection
2. **Step 2**: Language selection modal
3. **Step 3**: Document configuration form
4. **Step 4**: ID card generation process
5. **Step 5**: Guide information input
6. **Step 6**: Document generation confirmation
7. **Step 7**: Document saving and printing

### Creditors Overview Process (5 screenshots):

1. **Step 1**: Creditors dashboard main page
2. **Step 2**: Total credits by currency section
3. **Step 3**: Active creditors tab
4. **Step 4**: Inactive creditors tab
5. **Step 5**: Pagination and search functionality

### Adding New Creditor Process (7 screenshots):

1. **Step 1**: "Add New Creditor" button
2. **Step 2**: Add creditor modal opening
3. **Step 3**: Personal information fields
4. **Step 4**: Balance and currency selection
5. **Step 5**: Main account selection
6. **Step 6**: Deduction settings
7. **Step 7**: Creditor creation confirmation

### Processing Creditor Payments (8 screenshots):

1. **Step 1**: Payment button in creditors table
2. **Step 2**: Payment modal opening
3. **Step 3**: Creditor details review
4. **Step 4**: Payment amount input
5. **Step 5**: Currency selection
6. **Step 6**: Exchange rate configuration
7. **Step 7**: Main account and receipt details
8. **Step 8**: Payment confirmation

### Creditor Transactions Management (6 screenshots):

1. **Step 1**: Transactions list button
2. **Step 2**: Transactions modal opening
3. **Step 3**: Transaction list view
4. **Step 4**: Edit transaction button
5. **Step 5**: Edit transaction modal
6. **Step 6**: Transaction edit confirmation

### Creditor Status Management (5 screenshots):

1. **Step 1**: Active/Inactive tabs
2. **Step 2**: Delete creditor button
3. **Step 3**: Deletion confirmation
4. **Step 4**: Print statement button
5. **Step 5**: Creditor status management

### Debtors Overview Process (5 screenshots):

1. **Step 1**: Debtors dashboard main page
2. **Step 2**: Total debts by currency section
3. **Step 3**: Active debtors tab
4. **Step 4**: Inactive debtors tab
5. **Step 5**: Pagination and search functionality

### Adding New Debtor Process (7 screenshots):

1. **Step 1**: "Add New Debtor" button
2. **Step 2**: Add debtor modal opening
3. **Step 3**: Personal information fields
4. **Step 4**: Balance and currency selection
5. **Step 5**: Main account selection
6. **Step 6**: Deduction settings
7. **Step 7**: Debtor creation confirmation

### Processing Debtor Payments (8 screenshots):

1. **Step 1**: Payment button in debtors table
2. **Step 2**: Payment modal opening
3. **Step 3**: Debtor details review
4. **Step 4**: Payment amount input
5. **Step 5**: Currency selection
6. **Step 6**: Exchange rate configuration
7. **Step 7**: Main account and receipt details
8. **Step 8**: Payment confirmation

### Debtor Transactions Management (6 screenshots):

1. **Step 1**: Transactions list button
2. **Step 2**: Transactions modal opening
3. **Step 3**: Transaction list view
4. **Step 4**: Edit transaction button
5. **Step 5**: Edit transaction modal
6. **Step 6**: Transaction edit confirmation

### Debtor Status Management (5 screenshots):

1. **Step 1**: Active/Inactive tabs
2. **Step 2**: Deactivate debtor button
3. **Step 3**: Deactivation confirmation
4. **Step 4**: Print statement button
5. **Step 5**: Print agreement button

### Additional Payments Overview Process (5 screenshots):

1. **Step 1**: Additional payments main dashboard
2. **Step 2**: Payment table overview
3. **Step 3**: Table column details explanation
4. **Step 4**: Payment status indicators
5. **Step 5**: Action buttons functionality

### Creating Additional Payments Process (9 screenshots):

1. **Step 1**: "Add New Payment" button
2. **Step 2**: Payment type input field
3. **Step 3**: Main account selection dropdown
4. **Step 4**: Payment description textarea
5. **Step 5**: Base amount input
6. **Step 6**: Sold amount input
7. **Step 7**: Currency selection
8. **Step 8**: Supplier/Client linking options
9. **Step 9**: Save payment confirmation

### Managing Additional Payment Transactions (9 screenshots):

1. **Step 1**: Add transaction button in payment table
2. **Step 2**: Payment summary section
3. **Step 3**: Transaction date picker
4. **Step 4**: Transaction time selection
5. **Step 5**: Transaction amount input
6. **Step 6**: Transaction currency dropdown
7. **Step 7**: Exchange rate input (when currencies differ)
8. **Step 8**: Transaction description textarea
9. **Step 9**: Transaction history table

### JV Payments Overview Process (5 screenshots):

1. **Step 1**: JV Payments main dashboard
2. **Step 2**: Payment table overview
3. **Step 3**: Table column details explanation
4. **Step 4**: Action buttons functionality
5. **Step 5**: Payment status indicators

### Creating JV Payments Process (10 screenshots):

1. **Step 1**: "Add New Payment" button
2. **Step 2**: JV payment name input field
3. **Step 3**: Client selection dropdown
4. **Step 4**: Supplier selection dropdown
5. **Step 5**: Currency selection
6. **Step 6**: Payment amount input
7. **Step 7**: Exchange rate input (when currencies differ)
8. **Step 8**: Receipt number input
9. **Step 9**: Remarks textarea
10. **Step 10**: Process payment confirmation

### Managing JV Payment Details (5 screenshots):

1. **Step 1**: View payment details button
2. **Step 2**: Payment details modal overview
3. **Step 3**: Edit payment button and modal
4. **Step 4**: Modifiable payment fields
5. **Step 5**: Delete payment confirmation modal

### Sarafi Overview Process (5 screenshots):

1. **Step 1**: Sarafi dashboard main page
2. **Step 2**: Authentication and security flow
3. **Step 3**: Message management system
4. **Step 4**: Currency totals and dashboard widgets
5. **Step 5**: Action buttons and navigation

### Deposits and Withdrawals Process (10 screenshots):

1. **Step 1**: "New Deposit" button
2. **Step 2**: Customer selection dropdown
3. **Step 3**: Main account selection
4. **Step 4**: Amount input
5. **Step 5**: Currency selection
6. **Step 6**: Notes and reference input
7. **Step 7**: Receipt upload
8. **Step 8**: Deposit confirmation
9. **Step 9**: Transaction table view
10. **Step 10**: Transaction details modal

### Hawala and Currency Exchange Process (10 screenshots):

1. **Step 1**: Hawala transfer button
2. **Step 2**: Sender selection
3. **Step 3**: Amount and currency input
4. **Step 4**: Commission calculation
5. **Step 5**: Secret code generation
6. **Step 6**: Currency exchange button
7. **Step 7**: From and to currency selection
8. **Step 8**: Exchange rate input
9. **Step 9**: Transaction confirmation
10. **Step 10**: Transaction history view

### Hawala Transfer Process (8 screenshots):

1. **Step 1**: Sarafi dashboard with Hawala transfer button
2. **Step 2**: Hawala transfer modal opening
3. **Step 3**: Sender selection dropdown
4. **Step 4**: Transfer amount and currency inputs
5. **Step 5**: Commission amount calculation
6. **Step 6**: Secret code generation
7. **Step 7**: Main account selection
8. **Step 8**: Hawala transfer confirmation

### Currency Exchange Process (8 screenshots):

1. **Step 1**: Sarafi dashboard with currency exchange button
2. **Step 2**: Currency exchange modal opening
3. **Step 3**: Customer selection dropdown
4. **Step 4**: From currency amount input
5. **Step 5**: To currency amount calculation
6. **Step 6**: Exchange rate input
7. **Step 7**: Optional notes textarea
8. **Step 8**: Currency exchange confirmation

### Sarafi Deposits Process (10 screenshots):

1. **Step 1**: Sarafi dashboard with deposit button
2. **Step 2**: "New Deposit" button and modal opening
3. **Step 3**: Customer selection dropdown
4. **Step 4**: Main account selection
5. **Step 5**: Amount input field
6. **Step 6**: Currency selection
7. **Step 7**: Reference number input
8. **Step 8**: Optional notes textarea
9. **Step 9**: Receipt upload interface
10. **Step 10**: Deposit confirmation and success message

### Sarafi Withdrawals Process (10 screenshots):

1. **Step 1**: Sarafi dashboard with withdrawal button
2. **Step 2**: "New Withdrawal" button and modal opening
3. **Step 3**: Customer selection with balance verification
4. **Step 4**: Main account selection
5. **Step 5**: Amount input field (with balance check)
6. **Step 6**: Currency selection
7. **Step 7**: Reference number input
8. **Step 8**: Optional notes textarea
9. **Step 9**: Receipt upload interface
10. **Step 10**: Withdrawal confirmation and success message

### Salary Management Overview Process (5 screenshots):

1. **Step 1**: Salary management dashboard main page
2. **Step 2**: Employee salary records table
3. **Step 3**: Action dropdown functionality
4. **Step 4**: Salary record status indicators
5. **Step 5**: Filtering and search capabilities

### Adding New Salary Record Process (8 screenshots):

1. **Step 1**: "Add New Salary Record" button
2. **Step 2**: Employee selection dropdown
3. **Step 3**: Base salary input field
4. **Step 4**: Currency selection
5. **Step 5**: Joining date input
6. **Step 6**: Payment day selection
7. **Step 7**: Form validation checks
8. **Step 8**: Successful salary record creation

### Editing Salary Records Process (7 screenshots):

1. **Step 1**: Action dropdown in salary records table
2. **Step 2**: "Edit Salary" modal opening
3. **Step 3**: Base salary modification
4. **Step 4**: Currency change
5. **Step 5**: Payment day adjustment
6. **Step 6**: Employment status toggle
7. **Step 7**: Successful salary record update

### Salary Bonuses and Deductions Process (10 screenshots):

1. **Step 1**: "Manage Bonuses" button
2. **Step 2**: Bonus addition form
3. **Step 3**: Bonus amount input
4. **Step 4**: Bonus reason selection
5. **Step 5**: "Manage Deductions" button
6. **Step 6**: Deduction amount input
7. **Step 7**: Deduction reason selection
8. **Step 8**: Bonus calculation preview
9. **Step 9**: Deduction calculation preview
10. **Step 10**: Final salary adjustment confirmation

### Payroll Reporting Process (6 screenshots):

1. **Step 1**: "Print Group Payroll" button
2. **Step 2**: Payroll report generation options
3. **Step 3**: Date range selection
4. **Step 4**: Employee filter options
5. **Step 5**: Report preview interface
6. **Step 6**: Printing and export options

### Payroll Reporting Access and Navigation (5 screenshots):

1. **Step 1**: Salary management dashboard
2. **Step 2**: "Print Group Payroll" button location
3. **Step 3**: Payroll reporting landing page
4. **Step 4**: Navigation menu for payroll section
5. **Step 5**: Initial report generation interface

### Payroll Report Customization (7 screenshots):

1. **Step 1**: Date range selection dropdown
2. **Step 2**: Monthly/Quarterly/Annual report options
3. **Step 3**: Custom date range input
4. **Step 4**: Employee filtering dropdown
5. **Step 5**: Department selection
6. **Step 6**: Employment status filter
7. **Step 7**: Customization preview

### Payroll Report Details (8 screenshots):

1. **Step 1**: Employee basic information display
2. **Step 2**: Base salary section
3. **Step 3**: Bonuses breakdown
4. **Step 4**: Deductions overview
5. **Step 5**: Net salary calculation
6. **Step 6**: Payment method details
7. **Step 7**: Payment date information
8. **Step 8**: Full report preview

### Payroll Report Export and Sharing (6 screenshots):

1. **Step 1**: Export options menu
2. **Step 2**: PDF export process
3. **Step 3**: Excel export process
4. **Step 4**: CSV export process
5. **Step 5**: Print preview
6. **Step 6**: Email report interface

### Payroll Report Security (5 screenshots):

1. **Step 1**: Access control login
2. **Step 2**: User role verification
3. **Step 3**: Sensitive information masking
4. **Step 4**: Audit trail logging
5. **Step 5**: Export and sharing logs

### Salary Bonuses Management Process (8 screenshots):

1. **Step 1**: "Manage Bonuses" button in salary management page
2. **Step 2**: Employee selection dropdown
3. **Step 3**: Bonus amount input field
4. **Step 4**: Bonus type selection
5. **Step 5**: Bonus reason textarea
6. **Step 6**: Effective date selection
7. **Step 7**: Bonus confirmation preview
8. **Step 8**: Successful bonus addition confirmation

### Salary Deductions Management Process (8 screenshots):

1. **Step 1**: "Manage Deductions" button in salary management page
2. **Step 2**: Employee selection dropdown
3. **Step 3**: Deduction amount input field
4. **Step 4**: Deduction type selection
5. **Step 5**: Deduction reason textarea
6. **Step 6**: Effective date selection
7. **Step 7**: Deduction confirmation preview
8. **Step 8**: Successful deduction addition confirmation

### Salary Adjustment Calculation Process (6 screenshots):

1. **Step 1**: Base salary display
2. **Step 2**: Total bonuses calculation
3. **Step 3**: Total deductions calculation
4. **Step 4**: Net salary computation
5. **Step 5**: Salary adjustment summary
6. **Step 6**: Final payroll record update

---

## Tips for Good Screenshots

1. **Clean Interface**: Clear browser cache and use a clean interface
2. **Highlight Important Elements**: Use arrows or highlights to point to important buttons/fields
3. **Consistent Browser**: Use the same browser for all screenshots
4. **High Resolution**: Take screenshots at high resolution for clarity

### Visa Application Creation Process (10 screenshots):

1. **Step 1**: Visa management page with search functionality and "New Visa" button
2. **Step 2**: Clicking the "New Visa Application" button
3. **Step 3**: Business partner selection section (supplier, sold to, paid via)
4. **Step 4**: Applicant details section with all fields
5. **Step 5**: Dates section with date pickers
6. **Step 6**: Financial details section with base price, sold price, profit
7. **Step 7**: Description textarea
8. **Step 8**: Completed form with "Add Visa" button
9. **Step 9**: Updated visa applications list
10. **Step 10**: Visa application action buttons

### Visa Transaction Management Process (12 screenshots):

1. **Step 1**: Transaction management button in visa actions
2. **Step 2**: Transaction modal header with visa summary
3. **Step 3**: Payment status summary with multi-currency breakdown
4. **Step 4**: Transaction history table
5. **Step 5**: "New Transaction" button highlighted
6. **Step 6**: Date and time input fields
7. **Step 7**: Payment amount input field
8. **Step 8**: Currency dropdown
9. **Step 9**: Payment description textarea
10. **Step 10**: "Add Transaction" button
11. **Step 11**: Updated transaction history
12. **Step 12**: Transaction edit/delete actions

### Visa Refund Management Process (9 screenshots):

1. **Step 1**: Dropdown menu with "Refund Visa" option
2. **Step 2**: Refund modal with visa amount and profit
3. **Step 3**: Exchange rate input field
4. **Step 4**: Refund type selection buttons
5. **Step 5**: Partial refund amount input
6. **Step 6**: Refund reason textarea
7. **Step 7**: "Process Refund" button
8. **Step 8**: Refund confirmation message
9. **Step 9**: Visa refunds page

### File Browser Dashboard Overview (5 screenshots):

1. **Step 1**: Main file browser interface
2. **Step 2**: Breadcrumb navigation
3. **Step 3**: Search functionality
4. **Step 4**: View mode toggle (grid/list)
5. **Step 5**: Bulk actions bar

### File Upload Process (8 screenshots):

1. **Step 1**: "Upload Files" button location
2. **Step 2**: Upload modal opening
3. **Step 3**: Drag and drop zone
4. **Step 4**: File selection interface
5. **Step 5**: File list preview
6. **Step 6**: Upload progress bar
7. **Step 7**: Successful upload confirmation
8. **Step 8**: Uploaded file in file browser

### Folder Creation Process (5 screenshots):

1. **Step 1**: "New Folder" button
2. **Step 2**: New folder modal
3. **Step 3**: Folder name input
4. **Step 4**: Folder creation confirmation
5. **Step 5**: New folder in file browser

### File Filtering and Sorting (7 screenshots):

1. **Step 1**: Filter dropdown menu
2. **Step 2**: File type filtering (images, documents, etc.)
3. **Step 3**: Date range filtering
4. **Step 4**: Sort dropdown menu
5. **Step 5**: Sorting by name
6. **Step 6**: Sorting by date
7. **Step 7**: Sorting by size

### File Management Actions (10 screenshots):

1. **Step 1**: File action buttons
2. **Step 2**: Preview modal
3. **Step 3**: Download functionality
4. **Step 4**: Rename file process
5. **Step 5**: Delete file confirmation
6. **Step 6**: Move file functionality
7. **Step 7**: Copy file process
8. **Step 8**: Bulk selection
9. **Step 9**: Bulk delete confirmation
10. **Step 10**: File details panel

### Letter Management Dashboard (5 screenshots):

1. **Step 1**: Main letter management interface
2. **Step 2**: Recent letters table
3. **Step 3**: Search and filter functionality
4. **Step 4**: Status and language indicators
5. **Step 5**: Action buttons overview

### Creating New Letter Process (8 screenshots):

1. **Step 1**: "Create New Letter" button
2. **Step 2**: Letter number input
3. **Step 3**: Date selection
4. **Step 4**: Company name input
5. **Step 5**: Language selection
6. **Step 6**: Subject line input
7. **Step 7**: Letter content textarea
8. **Step 8**: Letter creation confirmation

### Viewing Letter Details (6 screenshots):

1. **Step 1**: View letter details button
2. **Step 2**: Letter details modal
3. **Step 3**: Letter number and date display
4. **Step 4**: Company and language information
5. **Step 5**: Letter content preview
6. **Step 6**: Status and sender information

### Letter Viewing Process (7 screenshots):

1. **Step 1**: Recent letters table overview
2. **Step 2**: View button location in actions column
3. **Step 3**: Dropdown actions menu
4. **Step 4**: Hover preview functionality
5. **Step 5**: Letter details modal opening
6. **Step 6**: Detailed letter information display
7. **Step 7**: Sender and status information section

### Letter Export and Download Process (6 screenshots):

1. **Step 1**: Download icon in actions column
2. **Step 2**: "Download PDF" button in view modal
3. **Step 3**: Keyboard shortcut for export
4. **Step 4**: PDF export preview
5. **Step 5**: Download confirmation dialog
6. **Step 6**: Saved PDF file location and verification

### Budget Allocation Dashboard Overview (5 screenshots):

1. **Step 1**: Main budget allocation interface
2. **Step 2**: Total allocated funds summary
3. **Step 3**: Available and used funds breakdown
4. **Step 4**: Currency and account distribution
5. **Step 5**: Month and year filter functionality

### Creating Budget Allocation Process (8 screenshots):

1. **Step 1**: "New Allocation" button location
2. **Step 2**: Expense category selection
3. **Step 3**: Main account dropdown
4. **Step 4**: Amount input field
5. **Step 5**: Currency selection
6. **Step 6**: Allocation date picker
7. **Step 7**: Description input
8. **Step 8**: Allocation creation confirmation

### Adding Funds to Allocation (6 screenshots):

1. **Step 1**: "Fund" button on allocation card
2. **Step 2**: Additional funds modal opening
3. **Step 3**: Amount input
4. **Step 4**: Optional note textarea
5. **Step 5**: Currency display
6. **Step 6**: Fund addition confirmation

### Viewing Allocation Details (7 screenshots):

1. **Step 1**: View funds button
2. **Step 2**: Fund transactions modal
3. **Step 3**: Allocation details summary
4. **Step 4**: Transactions table
5. **Step 5**: Expense tracking
6. **Step 6**: Remaining funds display
7. **Step 7**: Transaction actions (delete/edit)

### Budget Allocation Deletion Process (5 screenshots):

1. **Step 1**: Delete button state (enabled/disabled)
2. **Step 2**: Deletion confirmation modal
3. **Step 3**: Remaining funds warning
4. **Step 4**: Deletion confirmation process
5. **Step 5**: Successful deletion notification

### Budget Rollover Process (6 screenshots):

1. **Step 1**: Pending allocations notification
2. **Step 2**: Budget rollover page
3. **Step 3**: Previous month's allocations
4. **Step 4**: Remaining funds overview
5. **Step 5**: Rollover confirmation
6. **Step 6**: Successful fund transfer

## Maintenance

1. **Keep Updated**: Update screenshots when the UI changes
2. **Version Control**: Track screenshot versions with your code
3. **File Size**: Optimize images to keep page load times reasonable
4. **Accessibility**: Always include meaningful alt text for screen readers

## Support

For questions about implementing screenshots in the tutorial system, refer to the main tutorial documentation or contact the development team.

### Asset Management Dashboard (5 screenshots):

1. **Step 1**: Main asset management interface
2. **Step 2**: Total assets and value summary cards
3. **Step 3**: Category distribution pie chart
4. **Step 4**: Asset status bar chart
5. **Step 5**: Quick action buttons

### Adding New Asset Process (8 screenshots):

1. **Step 1**: "Add New Asset" button
2. **Step 2**: Asset name and category input
3. **Step 3**: Purchase date selection
4. **Step 4**: Warranty details
5. **Step 5**: Purchase and current value input
6. **Step 6**: Location and serial number
7. **Step 7**: Status and condition selection
8. **Step 8**: Asset creation confirmation

### Viewing Asset Details (6 screenshots):

1. **Step 1**: View asset details button
2. **Step 2**: Basic asset information modal
3. **Step 3**: Financial details display
4. **Step 4**: Status and condition overview
5. **Step 5**: Depreciation progress bar
6. **Step 6**: Attached documents preview

### Asset Analytics and Reporting (4 screenshots):

1. **Step 1**: Category distribution pie chart
2. **Step 2**: Asset status bar chart
3. **Step 3**: Total asset value by currency
4. **Step 4**: Detailed asset analytics dashboard

### Advanced Asset Filtering (5 screenshots):

1. **Step 1**: Filter section overview
2. **Step 2**: Category dropdown selection
3. **Step 3**: Location search input
4. **Step 4**: Date range picker
5. **Step 5**: Filtered results display

### Supplier Management Dashboard (5 screenshots):

1. **Step 1**: Main supplier management interface
2. **Step 2**: Active and inactive supplier tabs
3. **Step 3**: Supplier information table overview
4. **Step 4**: Quick action buttons
5. **Step 5**: Status filtering functionality

### Adding New Supplier Process (8 screenshots):

1. **Step 1**: "Add New Supplier" button
2. **Step 2**: Supplier name input
3. **Step 3**: Contact person details
4. **Step 4**: Phone and email input
5. **Step 5**: Currency and balance selection
6. **Step 6**: Supplier type selection
7. **Step 7**: Address input
8. **Step 8**: Supplier creation confirmation

### Editing Supplier Information (6 screenshots):

1. **Step 1**: Edit supplier button
2. **Step 2**: Edit modal opening
3. **Step 3**: Modifying contact information
4. **Step 4**: Updating financial details
5. **Step 5**: Changing supplier type
6. **Step 6**: Saving changes confirmation

### Supplier Status Management (4 screenshots):

1. **Step 1**: Status change dropdown
2. **Step 2**: Active to inactive transition
3. **Step 3**: Inactive to active reactivation
4. **Step 4**: Status change confirmation

### Client Management Dashboard (5 screenshots):

1. **Step 1**: Main client management interface
2. **Step 2**: Client statistics overview
3. **Step 3**: Active and inactive client tabs
4. **Step 4**: Quick action buttons
5. **Step 5**: Dashboard statistics cards

### Adding New Client Process (8 screenshots):

1. **Step 1**: "Add New Client" button
2. **Step 2**: Client name input
3. **Step 3**: Email and phone details
4. **Step 4**: Password setup
5. **Step 5**: Address input
6. **Step 6**: USD and AFS balance selection
7. **Step 7**: Client type and status selection
8. **Step 8**: Client creation confirmation

### Client Search and Filtering (6 screenshots):

1. **Step 1**: Search input field
2. **Step 2**: Client type dropdown
3. **Step 3**: Search by name, email, or phone
4. **Step 4**: Filtered results display
5. **Step 5**: Quick filter buttons
6. **Step 6**: Advanced search options

### Editing Client Information (6 screenshots):

1. **Step 1**: Edit client button
2. **Step 2**: Edit modal opening
3. **Step 3**: Modifying contact information
4. **Step 4**: Updating client type
5. **Step 5**: Changing account status
6. **Step 6**: Saving changes confirmation

### Expense Management Dashboard (5 screenshots):

1. **Step 1**: Main expense management interface
2. **Step 2**: Expense categories overview
3. **Step 3**: Quick action buttons
4. **Step 4**: Total expenses and income summary
5. **Step 5**: Filtering and date range selection

### Adding Expense Categories (4 screenshots):

1. **Step 1**: "Add Category" button location
2. **Step 2**: Category creation modal
3. **Step 3**: Category name input
4. **Step 4**: Category save confirmation

### Recording Expenses (8 screenshots):

1. **Step 1**: "Add Expense" button
2. **Step 2**: Expense category selection
3. **Step 3**: Date picker
4. **Step 4**: Description input
5. **Step 5**: Amount and currency selection
6. **Step 6**: Main account selection
7. **Step 7**: Receipt upload
8. **Step 8**: Expense save confirmation

### Date Range Filtering (6 screenshots):

1. **Step 1**: Date range input fields
2. **Step 2**: Quick date range buttons
3. **Step 3**: Applying date filter
4. **Step 4**: Filtered expenses view
5. **Step 5**: Reset filter button
6. **Step 6**: Date range validation

### Financial Charts and Reporting (7 screenshots):

1. **Step 1**: Income overview chart
2. **Step 2**: Expense distribution chart
3. **Step 3**: Profit and loss chart
4. **Step 4**: Chart export button
5. **Step 5**: Chart export confirmation
6. **Step 6**: Comprehensive financial report generation
7. **Step 7**: Financial report download

### Export and Reporting Options (5 screenshots):

1. **Step 1**: Export chart as image
2. **Step 2**: Export financial data to Excel
3. **Step 3**: Comprehensive report export button
4. **Step 4**: Export date range selection
5. **Step 5**: Exported file download and confirmation