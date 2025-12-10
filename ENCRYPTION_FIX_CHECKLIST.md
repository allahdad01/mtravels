# Chat Encryption and Message Display Fix Checklist

## Problem
Messages are not displaying between sender and recipient after encryption was added.

## Root Causes Identified
1. **Database schema missing encryption columns** - chat_messages table needs:
   - `encrypted_content` LONGTEXT
   - `is_encrypted` TINYINT(1)
   - `encryption_key_id` INT(11)
   - `tenant_id_from` INT(11) (may need to be moved before to_user_id)

2. **Decryption using wrong tenant ID** - FIXED in `/api/messages.php`
   - Recipients were using their own tenant_id to decrypt
   - Needed to use sender's tenant_id (tenant_id_from) for decryption

3. **Contact list showing encrypted messages** - FIXED in `/api/contacts.php`
   - Last message preview was showing encrypted binary data
   - Added decryption logic to display readable preview

## Fix Steps

### Step 1: Verify/Add Database Columns
Run this SQL to add missing columns:

```sql
-- Check if columns exist first, then add them
ALTER TABLE `chat_messages` ADD COLUMN `encrypted_content` LONGTEXT NULL AFTER `content`;
ALTER TABLE `chat_messages` ADD COLUMN `is_encrypted` TINYINT(1) DEFAULT 0 AFTER `encrypted_content`;
ALTER TABLE `chat_messages` ADD COLUMN `encryption_key_id` INT(11) NULL AFTER `is_encrypted`;

-- Only if tenant_id_from doesn't exist yet
-- ALTER TABLE `chat_messages` ADD COLUMN `tenant_id_from` INT(11) NOT NULL DEFAULT 0 AFTER `to_user_id`;
```

### Step 2: Verify Applied Fixes
✅ `/api/messages.php` - Lines 86-107 and 196-209
   - Uses `tenant_id_from` when decrypting messages
   - Encrypts messages with sender's tenant_id

✅ `/api/contacts.php` - Lines 127-156
   - Decrypts last message preview for contact list
   - Uses `tenant_id_from` for decryption

### Step 3: Test Message Flow
1. User A sends message to User B
   - Message encrypted with User A's tenant key
   - Stored with `encrypted_content`, `is_encrypted=1`, `encryption_key_id=X`, `tenant_id_from=A_tenant_id`

2. User B receives message
   - Calls `/api/messages.php?peer_id=A_id`
   - API decrypts using `tenant_id_from` (User A's tenant)
   - Returns decrypted content

3. Contact list shows preview
   - `/api/contacts.php` fetches and decrypts last message
   - Shows readable preview in contact list

## Files Modified
- ✅ `/api/messages.php` - Decryption fix (lines 92-107)
- ✅ `/api/contacts.php` - Last message decryption (lines 127-156)
- ⏳ Database - Add encryption columns if missing

## Verification Queries
```sql
-- Check if encryption columns exist
SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS 
WHERE TABLE_NAME = 'chat_messages' 
AND COLUMN_NAME IN ('encrypted_content', 'is_encrypted', 'encryption_key_id', 'tenant_id_from');

-- Check recent messages
SELECT id, from_user_id, to_user_id, tenant_id_from, is_encrypted, content 
FROM chat_messages 
ORDER BY created_at DESC LIMIT 10;

-- Check encrypted message count
SELECT COUNT(*) as encrypted_messages FROM chat_messages WHERE is_encrypted = 1;
```

## Next Steps
1. Run database schema update
2. Test sending/receiving messages
3. Verify contact list shows message previews
4. Check browser console for any errors
