# Finance Tracker - Quick Setup Guide

## 📋 Installation Checklist

### Step 1: Create Database Table

Run the migration file:

```bash
# Option A: Via MySQL command line
mysql -u root -p travelagency_saas < migrations/create_finance_tracker_table.sql

# Option B: Via phpMyAdmin
1. Go to phpMyAdmin
2. Select database 'travelagency_saas'
3. Go to "SQL" tab
4. Copy/paste the SQL from migrations/create_finance_tracker_table.sql
5. Click "Go"
```

**Expected Output:** "Query executed successfully"

### Step 2: Verify Files Exist

Check these files are in place:

```
✓ migrations/create_finance_tracker_table.sql
✓ api/finance/finance_tracker_actions.php
✓ admin/finance_tracker.php
✓ modals/finance_tracker_widget.php
✓ docs/FINANCE_TRACKER.md
✓ docs/FINANCE_TRACKER_SETUP.md (this file)
```

### Step 3: Set File Permissions

Ensure proper permissions:

```bash
chmod 644 api/finance/finance_tracker_actions.php
chmod 644 admin/finance_tracker.php
chmod 644 modals/finance_tracker_widget.php
```

### Step 4: Verify Database Connection

Test the connection:

```php
<?php
// Quick test script
require_once 'config.php';

try {
    $stmt = $pdo->prepare("SELECT 1 FROM finance_tracker LIMIT 1");
    $stmt->execute();
    echo "✓ Database connection successful!";
} catch (Exception $e) {
    echo "✗ Error: " . $e->getMessage();
}
?>
```

---

## 🧪 Testing the Installation

### Test 1: Access the Tracker

1. Go to: `http://localhost/mtravels/admin/finance_tracker.php`
2. You should see the dashboard
3. Click **"🔄 Refresh Data"** button
4. Should load without errors

### Test 2: Add a Test Transaction

```
1. Click "➕ Add Income"
2. Date: Today
3. Amount: 100
4. Currency: USD
5. Description: "Test transaction"
6. Click "Save Transaction"
```

**Expected Result:**
- Success message appears
- USD Balance updated to $100.00
- Transaction appears in recent list

### Test 3: Add Expense

```
1. Click "➖ Add Expense"
2. Amount: 25
3. Currency: USD
4. Description: "Test expense"
5. Save
```

**Expected Result:**
- USD Balance now shows $75.00
- Today's Expense shows $25.00

### Test 4: Currency Exchange

```
1. Click "💱 Exchange Currency"
2. From: USD
3. To: AFS
4. Amount: 50
5. Exchange Rate: 75
6. Click "Exchange Now"
```

**Expected Results:**
- USD Balance: $25.00 (50 deducted)
- AFS Balance: ₨3,750.00 (7,500 - 3,750)
- Two new transactions in list

### Test 5: Edit Transaction

```
1. Click "✏️" on any transaction
2. Change amount
3. Click "Save Transaction"
```

**Expected Result:**
- Balance recalculates immediately
- Recent list updates

### Test 6: Delete Transaction

```
1. Click "🗑️" on any transaction
2. Confirm deletion
```

**Expected Result:**
- Transaction removed
- Balance adjusted

### Test 7: Widget Embedding

Add to your dashboard:

```php
<?php
session_start();
include '../modals/finance_tracker_widget.php';
?>
```

**Expected Result:**
- Widget loads on page
- Shows current balances
- Auto-refreshes every 2 minutes

---

## 🔍 Verification Queries

Run these SQL queries to verify data:

### Check Table Exists
```sql
DESCRIBE finance_tracker;
```

**Expected Result:** 11 rows with column definitions

### Check Records
```sql
SELECT COUNT(*) as total_transactions,
       SUM(CASE WHEN type='income' THEN amount ELSE 0 END) as total_income,
       SUM(CASE WHEN type='expense' THEN amount ELSE 0 END) as total_expense
FROM finance_tracker
WHERE tenant_id = 1 AND branch_id = 1;
```

### Check Activity Logging
```sql
SELECT * FROM activity_log 
WHERE table_name = 'finance_tracker' 
ORDER BY created_at DESC 
LIMIT 5;
```

---

## 🚀 Production Setup

### Step 1: User Role Setup

Ensure Finance Admin users have correct role:

```sql
-- Check user roles
SELECT id, email, role FROM users WHERE role LIKE '%finance%';

-- If needed, update user role
UPDATE users SET role = 'finance_admin' WHERE email = 'finance@example.com';
```

### Step 2: Menu Integration

Add to admin navigation (e.g., in your header/sidebar):

```html
<!-- In your admin menu -->
<a href="finance_tracker.php" class="nav-item">
    <span class="icon">💰</span>
    <span class="label">Finance Tracker</span>
</a>
```

### Step 3: Backup Setup

Create automated backups:

```bash
# Add to cron job
0 2 * * * mysqldump -u root -p travelagency_saas finance_tracker > /backups/finance_tracker_$(date +\%Y\%m\%d).sql
```

### Step 4: Access Control

Verify in `admin/finance_tracker.php`:

```php
// Line ~15-18: Check allowed roles
$allowed_roles = ['admin', 'finance_admin', 'super_admin'];
if (!isset($_SESSION['role']) || !in_array($_SESSION['role'], $allowed_roles)) {
    header('Location: ../access_denied.php');
    exit;
}
```

Adjust roles as needed for your system.

---

## 🐛 Troubleshooting

### Issue: "Table doesn't exist"

**Solution:**
```sql
-- Run migration again
mysql -u root -p travelagency_saas < migrations/create_finance_tracker_table.sql

-- Or manually in phpMyAdmin SQL tab
```

### Issue: "Access Denied"

**Solution:**
1. Check user role is 'finance_admin' or higher
2. Check `$_SESSION['tenant_id']` and `$_SESSION['branch_id']` are set
3. Try logging out and back in

### Issue: "CSRF token validation failed"

**Solution:**
1. Ensure session cookies are enabled
2. Clear browser cache
3. Try in incognito/private mode
4. Check `security.php` is loaded

### Issue: "API returns 500 error"

**Solution:**
1. Check PHP error logs: `logs/php_errors.log`
2. Verify `finance_tracker_actions.php` is readable
3. Check database connection in `config.php`
4. Test with simple query first

### Issue: "Balances not calculating correctly"

**Solution:**
```sql
-- Verify data integrity
SELECT type, currency, COUNT(*), SUM(amount) 
FROM finance_tracker 
WHERE tenant_id = 1 AND branch_id = 1 
GROUP BY type, currency;

-- Recalculate balances manually
SELECT 
    (SELECT COALESCE(SUM(amount), 0) FROM finance_tracker 
     WHERE type='income' AND currency='usd' AND tenant_id=1 AND branch_id=1) -
    (SELECT COALESCE(SUM(amount), 0) FROM finance_tracker 
     WHERE type='expense' AND currency='usd' AND tenant_id=1 AND branch_id=1) 
    as USD_Balance;
```

---

## 📊 Sample Data Setup

For testing with real-looking data:

```sql
-- Insert sample transactions
INSERT INTO finance_tracker (date, type, amount, currency, description, branch_id, tenant_id, created_by) VALUES
('2026-03-31', 'income', 500, 'usd', 'Ticket sale - Client A', 1, 1, 1),
('2026-03-31', 'income', 1000, 'usd', 'Visa payment - Client B', 1, 1, 1),
('2026-03-31', 'expense', 150, 'usd', 'Office supplies', 1, 1, 1),
('2026-03-31', 'expense', 75, 'usd', 'Transport', 1, 1, 1),
('2026-03-31', 'income', 37500, 'afs', 'AFS income', 1, 1, 1),
('2026-03-31', 'expense', 7500, 'afs', 'AFS expense', 1, 1, 1);
```

After inserting, balances should show:
- USD Balance: $1,275.00
- AFS Balance: ₨30,000.00

---

## 📱 Widget Testing

To test the widget on your dashboard:

```php
<?php
session_start();
?>
<!DOCTYPE html>
<html>
<head>
    <link rel="stylesheet" href="assets/css/bootstrap.min.css">
</head>
<body>
    <div class="container mt-5">
        <h1>Dashboard Test</h1>
        
        <!-- Finance Widget -->
        <?php include 'modals/finance_tracker_widget.php'; ?>
        
        <!-- Rest of dashboard -->
    </div>
</body>
</html>
```

---

## ✅ Pre-Launch Checklist

- [ ] Database table created successfully
- [ ] All files in correct locations
- [ ] Can access finance_tracker.php
- [ ] Can add transactions
- [ ] Balances calculate correctly
- [ ] Activity logging works
- [ ] Widget loads and updates
- [ ] User role restrictions work
- [ ] CSRF protection working
- [ ] Error handling tested
- [ ] Clear all function works (with confirmation)
- [ ] API endpoints respond correctly
- [ ] Currency exchange tested
- [ ] Backups configured
- [ ] Access controls verified
- [ ] Documentation reviewed

---

## 🔒 Security Checklist

- [ ] Only Finance Admin role can access
- [ ] CSRF tokens validated on all POST
- [ ] SQL injection prevented (prepared statements)
- [ ] Multi-tenant isolation enforced
- [ ] Activity audit trail enabled
- [ ] Sensitive operations require confirmation
- [ ] IP addresses logged in audit trail
- [ ] Clear all requires confirmation string

---

## 📞 Support Resources

- **Full Documentation:** `docs/FINANCE_TRACKER.md`
- **API Reference:** See docs/FINANCE_TRACKER.md - API Endpoints section
- **Database:** Check `activity_log` table for error details
- **Logs:** Check `logs/` directory for error messages

---

**Setup Date:** ___________  
**Verified By:** ___________  
**Status:** ☐ Ready for Production
