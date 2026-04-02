# 💰 Finance Admin Quick Tracker

**Version:** 1.0  
**Created:** 2026  
**Status:** Production Ready

---

## Overview

Finance Admin Quick Tracker is a **simple, lightweight finance tracking widget** designed exclusively for Finance Admins. It provides quick, temporary tracking of income and expenses without the complexity of a full accounting system.

### Key Features
- ✅ Track income and expenses separately
- ✅ Support for USD and AFS currencies
- ✅ Simple currency exchange tracking
- ✅ Real-time balance calculations
- ✅ Today's income/expense summary
- ✅ Recent transaction history
- ✅ Activity logging for audit trails
- ✅ Clear all data (for settlement)
- ✅ Embeddable widget component

---

## Database Structure

### Table: `finance_tracker`

```sql
CREATE TABLE IF NOT EXISTS `finance_tracker` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    
    `date` DATE NOT NULL,
    
    `type` ENUM('income', 'expense') NOT NULL,
    
    `amount` DECIMAL(12, 2) NOT NULL,
    `currency` ENUM('usd', 'afs') NOT NULL DEFAULT 'usd',
    
    `description` TEXT,
    
    `branch_id` INT NOT NULL,
    `tenant_id` INT NOT NULL,
    `created_by` INT NOT NULL,
    
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    -- Indexes for faster queries
    INDEX idx_branch_tenant (`branch_id`, `tenant_id`),
    INDEX idx_date (`date`),
    INDEX idx_type_currency (`type`, `currency`),
    INDEX idx_created_by (`created_by`),
    
    -- Foreign key constraints
    CONSTRAINT fk_finance_tracker_branch FOREIGN KEY (`branch_id`) REFERENCES `branches`(`id`) ON DELETE CASCADE,
    CONSTRAINT fk_finance_tracker_tenant FOREIGN KEY (`tenant_id`) REFERENCES `tenants`(`id`) ON DELETE CASCADE,
    CONSTRAINT fk_finance_tracker_user FOREIGN KEY (`created_by`) REFERENCES `users`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

### Fields Explanation

| Field | Type | Purpose |
|-------|------|---------|
| `id` | INT | Unique transaction identifier |
| `date` | DATE | Transaction date |
| `type` | ENUM | Transaction type: 'income' or 'expense' |
| `amount` | DECIMAL(12,2) | Transaction amount |
| `currency` | ENUM | Currency: 'usd' or 'afs' |
| `description` | TEXT | Optional transaction notes |
| `branch_id` | INT | Branch isolation (multi-tenant) |
| `tenant_id` | INT | Tenant isolation (multi-tenant) |
| `created_by` | INT | User who created the transaction |
| `created_at` | TIMESTAMP | Record creation timestamp |
| `updated_at` | TIMESTAMP | Record update timestamp |

---

## Installation

### 1. Run Migration

Execute the migration to create the table:

```bash
mysql -u root -p travelagency_saas < migrations/create_finance_tracker_table.sql
```

Or manually execute the SQL in your database client.

### 2. Verify API Endpoint

Ensure the API file exists and is accessible:
- Location: `api/finance/finance_tracker_actions.php`
- Check file permissions: Should be readable and executable

### 3. Add Menu Item (Optional)

Add to your admin navigation menu:

```html
<li><a href="/admin/finance_tracker.php">💰 Finance Tracker</a></li>
```

---

## API Endpoints

All endpoints are in: `api/finance/finance_tracker_actions.php`

### Authentication
- **Required:** All requests must be authenticated (session)
- **CSRF Token:** Required for POST requests
- **Role Check:** Only Finance Admin roles allowed

### Endpoints

#### 1. Add Transaction
```
POST /api/finance/finance_tracker_actions.php
action: add_transaction

Parameters:
- date (required): YYYY-MM-DD format
- type (required): 'income' or 'expense'
- amount (required): Numeric, greater than 0
- currency (required): 'usd' or 'afs'
- description (optional): Text

Response:
{
  "success": true,
  "message": "Transaction added successfully",
  "transaction_id": 123
}
```

#### 2. Get Balances
```
GET /api/finance/finance_tracker_actions.php?action=get_balances

Response:
{
  "success": true,
  "usd_balance": 1500.50,
  "afs_balance": 112537.50,
  "today_income_usd": 500.00,
  "today_income_afs": 37500.00,
  "today_expense_usd": 100.00,
  "today_expense_afs": 7500.00
}
```

#### 3. Get Recent Transactions
```
GET /api/finance/finance_tracker_actions.php?action=get_recent_transactions&limit=10

Response:
{
  "success": true,
  "transactions": [
    {
      "id": 1,
      "date": "2026-03-31",
      "type": "income",
      "amount": "500.00",
      "currency": "usd",
      "description": "Ticket sale",
      "created_at": "2026-03-31 10:30:00"
    },
    ...
  ]
}
```

#### 4. Get Single Transaction
```
GET /api/finance/finance_tracker_actions.php?action=get_transaction&id=123

Response:
{
  "success": true,
  "transaction": { ... }
}
```

#### 5. Update Transaction
```
POST /api/finance/finance_tracker_actions.php
action: update_transaction

Parameters:
- id (required): Transaction ID
- date (required): YYYY-MM-DD format
- type (required): 'income' or 'expense'
- amount (required): Numeric
- currency (required): 'usd' or 'afs'
- description (optional): Text

Response:
{
  "success": true,
  "message": "Transaction updated successfully"
}
```

#### 6. Delete Transaction
```
POST /api/finance/finance_tracker_actions.php
action: delete_transaction

Parameters:
- id (required): Transaction ID

Response:
{
  "success": true,
  "message": "Transaction deleted successfully"
}
```

#### 7. Exchange Currency
```
POST /api/finance/finance_tracker_actions.php
action: exchange_currency

Parameters:
- from_currency (required): 'usd' or 'afs'
- to_currency (required): 'usd' or 'afs'
- from_amount (required): Numeric
- exchange_rate (required): Numeric (conversion factor)
- description (optional): Exchange description

Response:
{
  "success": true,
  "message": "Currency exchange recorded successfully",
  "from_amount": 100,
  "from_currency": "usd",
  "to_amount": 7500,
  "to_currency": "afs",
  "exchange_rate": 75
}
```

#### 8. Clear All Data
```
POST /api/finance/finance_tracker_actions.php
action: clear_all

Parameters:
- confirmation: Must be "CLEAR_ALL_FINANCE_DATA"

⚠️ Warning: This permanently deletes all records!

Response:
{
  "success": true,
  "message": "All finance tracker data cleared successfully",
  "deleted_count": 150
}
```

---

## Balance Calculation

### USD Balance Formula
```sql
CASE 
    WHEN type = 'income' AND currency = 'usd' THEN amount
    WHEN type = 'expense' AND currency = 'usd' THEN -amount
    ELSE 0
END
```

### AFS Balance Formula
```sql
CASE 
    WHEN type = 'income' AND currency = 'afs' THEN amount
    WHEN type = 'expense' AND currency = 'afs' THEN -amount
    ELSE 0
END
```

---

## Currency Exchange

### Simple Two-Record Method

When exchanging currencies (e.g., 100 USD → 7500 AFS at rate 75):

**Record 1: Expense**
- Type: expense
- Amount: 100
- Currency: usd
- Description: "Exchange: Bank exchange (rate: 75)"

**Record 2: Income**
- Type: income
- Amount: 7500
- Currency: afs
- Description: "Exchange: Bank exchange (rate: 75)"

This creates a natural audit trail showing currency conversion without requiring a separate "exchange" type.

---

## Usage Guide

### Accessing the Tracker

**URL:** `admin/finance_tracker.php`

**Dashboard Shows:**
- 💵 USD Balance (total)
- 💴 AFS Balance (total)
- 📈 Today's Income (by currency)
- 📉 Today's Expense (by currency)
- 📝 Recent Transactions (last 20)

### Adding Income

1. Click **"➕ Add Income"** button
2. Select date
3. Enter amount
4. Select currency (USD or AFS)
5. Add optional description
6. Click **"Save Transaction"**

### Adding Expense

1. Click **"➖ Add Expense"** button
2. Fill in form details
3. Click **"Save Transaction"**

### Currency Exchange

1. Click **"💱 Exchange Currency"** button
2. Select "From" and "To" currencies
3. Enter amount in source currency
4. Enter exchange rate
5. Preview shows converted amount
6. Click **"Exchange Now"**

### Editing Transaction

1. Click **"✏️"** button on transaction row
2. Modify fields in modal
3. Click **"Save Transaction"**

### Deleting Transaction

1. Click **"🗑️"** button on transaction row
2. Confirm deletion
3. Transaction removed and balance updated

### Clearing All Data

⚠️ **Important:** This action is irreversible!

1. Click **"🔴 Clear All"** button
2. Read the warning carefully
3. Type "CLEAR ALL" to confirm
4. Click **"Clear All Data"**
5. All records deleted, activity logged

---

## Embedding Widget

Use the reusable widget component in any dashboard:

```php
<?php
// In your dashboard file
session_start();
include '../modals/finance_tracker_widget.php';
?>
```

The widget displays:
- USD and AFS balances
- Today's income/expense
- Quick link to full tracker

---

## Activity Logging

All transactions are logged in the `activity_log` table:

| Field | Value |
|-------|-------|
| `user_id` | User who performed action |
| `action` | 'add', 'update', 'delete', 'delete_all' |
| `table_name` | 'finance_tracker' |
| `record_id` | Transaction ID |
| `old_values` | Previous state (JSON) |
| `new_values` | New state (JSON) |
| `ip_address` | User's IP address |
| `user_agent` | Browser/client info |
| `created_at` | Timestamp |
| `tenant_id` | Tenant isolation |
| `branch_id` | Branch isolation |

---

## Security Features

✅ **Authentication Required**
- All endpoints require active session
- Enforced via `enforce_auth()`

✅ **CSRF Protection**
- All POST requests validated
- Token verification on each request

✅ **Multi-Tenant Isolation**
- Data filtered by `branch_id` and `tenant_id`
- Users see only their branch data

✅ **Activity Audit Trail**
- Every action logged to activity_log
- Old and new values recorded
- IP address and user agent tracked

✅ **Input Validation**
- Amount must be > 0
- Currency limited to enum values
- Type limited to 'income' or 'expense'
- Dates validated

✅ **SQL Injection Prevention**
- Prepared statements used throughout
- PDO parameter binding

---

## Common Use Cases

### Settlement After Day
1. View all transactions for the day
2. Verify with physical cash count
3. Clear all data when settled
4. Start fresh next day

### Bank Reconciliation
1. Use add/edit to correct discrepancies
2. Record bank transfers as expense/income
3. Maintain audit trail for compliance

### Currency Conversion Tracking
1. Record when USD converted to AFS
2. Track conversion rate used
3. Maintain balanced records in both currencies

### Quick Daily Report
1. Check today's income/expense at a glance
2. Use widget on main dashboard
3. Drill into full tracker for details

---

## Troubleshooting

### Widget Not Loading
- Check if `finance_tracker_actions.php` exists
- Verify API endpoint is accessible
- Check browser console for errors
- Ensure session is active

### Balance Not Updating
- Click **"🔄 Refresh"** button
- Clear browser cache
- Check database connection
- Verify records were saved

### Permission Denied
- Check user role assignment
- Verify `allowed_roles` in finance_tracker.php
- Contact admin to grant Finance Admin role

### Clear All Not Working
- Ensure exact confirmation text "CLEAR ALL"
- Check CSRF token is valid
- Verify user has permission
- Check database disk space

---

## API Examples

### cURL Examples

**Add Income:**
```bash
curl -X POST "http://localhost/mtravels/api/finance/finance_tracker_actions.php" \
  -d "action=add_transaction&type=income&amount=500&currency=usd&date=2026-03-31&description=Ticket sale" \
  -b "PHPSESSID=your_session_id" \
  -H "Content-Type: application/x-www-form-urlencoded"
```

**Get Balances:**
```bash
curl "http://localhost/mtravels/api/finance/finance_tracker_actions.php?action=get_balances" \
  -b "PHPSESSID=your_session_id"
```

**Exchange Currency:**
```bash
curl -X POST "http://localhost/mtravels/api/finance/finance_tracker_actions.php" \
  -d "action=exchange_currency&from_currency=usd&to_currency=afs&from_amount=100&exchange_rate=75" \
  -b "PHPSESSID=your_session_id" \
  -H "Content-Type: application/x-www-form-urlencoded"
```

---

## Future Enhancements

Potential features for v2.0:

- 📊 Daily/weekly/monthly reports
- 📈 Charts and statistics
- 📧 Email summaries
- 🔐 Password-protected clear all
- 📱 Mobile app integration
- 🏦 Bank account linking
- 💳 Multi-account support
- 📑 Receipt storage
- 🤖 Auto-categorization
- 📋 Budget forecasting

---

## Support

For issues or questions:
1. Check activity_log for error details
2. Review browser console for JavaScript errors
3. Verify database integrity
4. Contact system administrator

---

## License

Part of MTravels SaaS Platform
All rights reserved © 2026

---

**Last Updated:** March 31, 2026  
**Maintained By:** Development Team
