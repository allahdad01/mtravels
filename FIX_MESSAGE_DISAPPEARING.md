# Fix: Messages Disappearing After Refresh

## Problem
When you send a message, it appears briefly but disappears after refresh. The message is NOT being saved to the database.

## Root Cause
The database `chat_messages` table is **missing the encryption columns** needed by the code:
- `encrypted_content`
- `is_encrypted`
- `encryption_key_id`

When the code tries to INSERT a message with encryption, the query fails silently because those columns don't exist.

## Solution

### Step 1: Check Status (Optional but Recommended)
Visit this page to check what's wrong:
```
http://your-domain/check_chat_status.php
```

This will show you:
- ✅ Which columns exist
- ❌ Which columns are missing
- 📊 Recent messages in database
- 🔧 Exact SQL to fix it

### Step 2: Add Missing Database Columns

**Option A: Via phpMyAdmin (easiest)**
1. Log in to phpMyAdmin
2. Select your database
3. Click on `chat_messages` table
4. Go to "SQL" tab
5. Copy and paste this SQL:

```sql
ALTER TABLE `chat_messages` 
ADD COLUMN `encrypted_content` LONGTEXT NULL AFTER `content`,
ADD COLUMN `is_encrypted` TINYINT(1) DEFAULT 0 AFTER `encrypted_content`,
ADD COLUMN `encryption_key_id` INT(11) NULL AFTER `is_encrypted`,
ADD COLUMN `tenant_id_from` INT(11) NOT NULL DEFAULT 0 AFTER `to_user_id`;
```

6. Click "Go"

**Option B: Via PHP Script (automatic)**
Visit this page:
```
http://your-domain/apply_encryption_migration.php
```

It will automatically add all missing columns and indexes.

**Option C: Via SQL File (manual)**
1. Run this SQL file in your database:
   ```
   migrations/simple_encryption_fix.sql
   ```

### Step 3: Verify the Fix
1. Visit `check_chat_status.php` again
2. All columns should now show ✅

### Step 4: Test
1. Go to chat
2. Send a message
3. **Refresh the page** (press F5)
4. **The message should now appear!**

## How It Works Now

### Before Fix (Broken)
```
User sends message → Code tries to encrypt → 
INSERT query with 'encrypted_content' column fails →
Message NOT saved → Disappears on refresh
```

### After Fix (Working)
```
User sends message → Code encrypts it → 
INSERT with encrypted_content succeeds →
Message saved to database → Appears after refresh
```

## Fallback Safety
Even if encryption fails, the code will:
1. Try to encrypt (if columns exist)
2. Fall back to plaintext if encryption fails
3. Fall back to basic insert if all else fails

This ensures messages are ALWAYS saved, even if something goes wrong.

## Files Changed
- ✅ `api/messages.php` - Added smart fallback logic
- ✅ `api/contacts.php` - Added message decryption
- 📝 Database - Needs columns added

## Quick Verification Query

Run this in phpMyAdmin to check if columns exist:

```sql
SELECT COLUMN_NAME, COLUMN_TYPE
FROM INFORMATION_SCHEMA.COLUMNS
WHERE TABLE_NAME = 'chat_messages'
AND COLUMN_NAME IN ('encrypted_content', 'is_encrypted', 'encryption_key_id', 'tenant_id_from');
```

Should return 4 rows. If not, columns are missing.

## Still Not Working?

1. **Check error logs:**
   - Look in your web server error log
   - Check `/apply_encryption_migration.php` output
   - Visit `/diagnose_chat_issue.php` for detailed diagnostics

2. **Verify database:**
   ```sql
   DESCRIBE chat_messages;
   ```
   
   Should show all columns including encryption ones

3. **Check messages are being saved:**
   ```sql
   SELECT * FROM chat_messages ORDER BY id DESC LIMIT 5;
   ```
   
   Should show recent messages from your tests

4. **Clear browser cache:**
   - Press Ctrl+Shift+Delete
   - Clear cache and cookies
   - Reload chat

## Support Files

- `check_chat_status.php` - Visual status check
- `diagnose_chat_issue.php` - Detailed diagnostics
- `apply_encryption_migration.php` - Auto-apply migration
- `migrations/simple_encryption_fix.sql` - SQL migration file
- `ENCRYPTION_FIX_CHECKLIST.md` - Technical details
