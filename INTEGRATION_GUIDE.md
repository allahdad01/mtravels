# Photo & Passport Upload - Integration Guide

This guide shows exactly where and how the feature integrates with the existing system.

## 🔗 Integration Points

### 1. Admin Umrah Page (`admin/umrah.php`)

#### CSS Integration
```php
<link rel="stylesheet" href="../css/document-upload.css">
```
**Location**: Line ~29, after other CSS imports

#### Modal Inclusion
```php
<?php include '../modals/umrah/member_documents_modal.php'; ?>
```
**Location**: Line ~768, with other modal includes

#### Action Menu Item
```php
<a class="dropdown-item" href="#" onclick="openMemberDocumentsModal(<?= $member['booking_id'] ?>, '<?= htmlspecialchars($member['name']) ?>'); return false;">
    <i class="feather icon-file-text mr-2 text-success"></i>Photo & Passport
</a>
```
**Location**: Line ~602, in the member action dropdown

#### Script References
```php
<script src="../js/member-document-upload.js"></script>
<script src="../js/umrah/open_documents_modal.js"></script>
```
**Location**: Lines ~796-798, after SweetAlert2

---

### 2. Client Umrah Page (`client/umrah.php`)

#### SweetAlert2 Library
```php
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11.7.32/dist/sweetalert2.all.min.js"></script>
```
**Location**: Line ~231, with other script imports

#### Documents Column Header
```php
<th>Documents</th>
```
**Location**: Line ~146, in the member table header

#### Documents Column Body
```php
<td>
    <div class="btn-group btn-group-sm" role="group">
        <?php if (!empty($member['photo_path'])): ?>
            <button type="button" class="btn btn-outline-info" title="View Photo" onclick="viewClientDocument('<?= htmlspecialchars($member['photo_path']) ?>', 'Photo')">
                <i class="feather icon-image"></i>
            </button>
        <?php endif; ?>
        <?php if (!empty($member['passport_path'])): ?>
            <button type="button" class="btn btn-outline-primary" title="View Passport" onclick="viewClientDocument('<?= htmlspecialchars($member['passport_path']) ?>', 'Passport')">
                <i class="feather icon-file-text"></i>
            </button>
        <?php endif; ?>
    </div>
</td>
```
**Location**: Line ~166, in the member table row

#### JavaScript Function
```javascript
function viewClientDocument(filePath, documentType) {
    if (!filePath) {
        alert('No file available');
        return;
    }

    if (filePath.endsWith('.pdf')) {
        window.open(filePath, '_blank');
    } else if (filePath.match(/\.(jpg|jpeg|png|gif)$/i)) {
        Swal.fire({
            title: documentType,
            imageUrl: filePath,
            imageAlt: documentType,
            width: '80%',
            showConfirmButton: false,
            html: '<a href="' + filePath + '" download class="btn btn-primary mt-3">Download</a>'
        });
    } else {
        window.location.href = filePath;
    }
}
```
**Location**: Line ~330, in the page scripts section

---

### 3. Database Integration

#### Migration File
**File**: `migrations/add_photo_passport_storage.sql`

**Executed with**:
```sql
ALTER TABLE `umrah_bookings` ADD COLUMN `photo_path` VARCHAR(500) DEFAULT NULL AFTER `remarks`;
ALTER TABLE `umrah_bookings` ADD COLUMN `passport_path` VARCHAR(500) DEFAULT NULL AFTER `photo_path`;
ALTER TABLE `umrah_bookings` ADD COLUMN `photo_uploaded_at` TIMESTAMP NULL AFTER `passport_path`;
ALTER TABLE `umrah_bookings` ADD COLUMN `passport_uploaded_at` TIMESTAMP NULL AFTER `photo_uploaded_at`;
CREATE INDEX idx_photo_passport ON `umrah_bookings`(`photo_path`, `passport_path`);
```

**Query Usage in APIs**:
```php
// Upload API
UPDATE umrah_bookings 
SET photo_path = ?, photo_uploaded_at = NOW() 
WHERE booking_id = ?

// Get API
SELECT photo_path, passport_path FROM umrah_bookings 
WHERE booking_id = ?

// Delete API
UPDATE umrah_bookings 
SET photo_path = NULL, photo_uploaded_at = NULL 
WHERE booking_id = ?
```

---

### 4. API Routes

#### Upload Endpoint
```
POST /api/upload_member_documents.php
├── Authentication Check
├── File Validation
├── Folder Creation
├── File Storage
└── Database Update
```

#### Retrieval Endpoint
```
GET /api/get_member_documents.php
├── Authentication Check
├── Data Retrieval
└── JSON Response
```

#### Delete Endpoint
```
POST /api/delete_member_document.php
├── Authentication Check
├── File Deletion
├── Database Cleanup
└── Response
```

---

### 5. File System Integration

#### Folder Structure
```
uploads/
├── .htaccess (auto-created by setup script)
└── {tenant_id}/
    └── {branch_id}/
        └── umrah/
            └── {family_id}/
                ├── photo_*.jpg
                ├── photo_*.png
                ├── passport_*.pdf
                └── ...
```

#### File Naming Convention
```
{document_type}_{booking_id}_{timestamp}_{random_string}.{extension}

Example:
photo_123_1706123456_a1b2c3d4.jpg
passport_123_1706123456_b2c3d4e5.pdf
```

---

## 📊 Data Flow Diagrams

### Admin Upload Flow
```
Admin UI
  ↓
Opens Modal
  ↓
Selects File
  ↓
Client Validation
  ↓
FormData Creation
  ↓
POST /api/upload_member_documents.php
  ↓
Server Validation
  ↓
Folder Creation (if needed)
  ↓
Move File
  ↓
Database Update
  ↓
JSON Response
  ↓
Modal Refresh
  ↓
Preview Display
```

### Client View Flow
```
Client Dashboard
  ↓
Umrah Management
  ↓
Family List
  ↓
Expand Family
  ↓
View Members
  ↓
Check photo_path & passport_path
  ↓
If NOT NULL → Show Icon
  ↓
Click Icon
  ↓
viewClientDocument()
  ↓
Fetch /api/get_member_documents.php (if needed)
  ↓
Display in Modal or New Tab
```

---

## 🔄 Request/Response Examples

### Upload Request
```javascript
const formData = new FormData();
formData.append('booking_id', 123);
formData.append('document_type', 'photo');
formData.append('file', fileObject);

fetch('/api/upload_member_documents.php', {
    method: 'POST',
    body: formData
})
```

### Upload Response
```json
{
    "success": true,
    "message": "Photo uploaded successfully",
    "file_path": "/uploads/1/1/umrah/5/photo_123_1706123456_a1b2c3d4.jpg",
    "file_name": "photo_123_1706123456_a1b2c3d4.jpg"
}
```

### Get Documents Request
```javascript
fetch('/api/get_member_documents.php?booking_id=123')
```

### Get Documents Response
```json
{
    "success": true,
    "photo_path": "/uploads/1/1/umrah/5/photo_123_1706123456_a1b2c3d4.jpg",
    "passport_path": null
}
```

### Delete Request
```javascript
const formData = new FormData();
formData.append('booking_id', 123);
formData.append('document_type', 'photo');

fetch('/api/delete_member_document.php', {
    method: 'POST',
    body: formData
})
```

### Delete Response
```json
{
    "success": true,
    "message": "Photo deleted successfully"
}
```

---

## 🔐 Session & Authorization

All APIs check:
```php
// Session verification
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

// Tenant/Branch isolation
$tenant_id = $_SESSION['tenant_id'];
$branch_id = $_SESSION['branch_id'];

// Booking ownership
$stmt = $pdo->prepare("
    SELECT * FROM umrah_bookings 
    WHERE booking_id = ? 
    AND tenant_id = ? 
    AND branch_id = ?
");
```

---

## 🎯 User Workflows

### Admin Workflow
1. Login to Admin Dashboard
2. Navigate to Umrah Management
3. Find desired family/member
4. Click member actions dropdown
5. Select "Photo & Passport"
6. Upload files in modal
7. Files saved and visible in preview
8. Can delete or update anytime

### Client Workflow
1. Login to Client Dashboard
2. Navigate to Umrah
3. Click "Family List"
4. Expand a family
5. View members table
6. See "Documents" column
7. Click photo or passport icon
8. View or download file
9. Can view anytime

---

## 🛠️ Development Integration

### Adding to Your Module

If adding to other modules, follow this pattern:

```php
// In your PHP file
$stmt = $pdo->prepare("
    SELECT booking_id, name, photo_path, passport_path 
    FROM umrah_bookings 
    WHERE family_id = ?
");

// In your HTML
<?php if (!empty($member['photo_path'])): ?>
    <a href="<?= htmlspecialchars($member['photo_path']) ?>" target="_blank">
        View Photo
    </a>
<?php endif; ?>

// In your JavaScript
function viewDocument(filePath) {
    if (filePath.endsWith('.pdf')) {
        window.open(filePath, '_blank');
    } else {
        Swal.fire({
            imageUrl: filePath,
            // ... other options
        });
    }
}
```

---

## 📋 Configuration Settings

### File Limits (in `api/upload_member_documents.php`)
```php
$allowed_types = ['image/jpeg', 'image/png', 'image/gif', 'application/pdf'];
$max_file_size = 5 * 1024 * 1024; // 5MB
```

### Folder Structure (customizable)
```php
$base_upload_dir = __DIR__ . '/../uploads';
$tenant_dir = $base_upload_dir . '/' . $tenant_id;
$branch_dir = $tenant_dir . '/' . $branch_id;
$umrah_dir = $branch_dir . '/umrah';
$family_dir = $umrah_dir . '/' . $family_id;
```

### File Naming (customizable)
```php
$document_type = 'photo'; // or 'passport'
$timestamp = time();
$random_string = substr(md5(mt_rand()), 0, 8);
$new_filename = $document_type . '_' . $booking_id . '_' . $timestamp . '_' . $random_string . '.' . $file_extension;
```

---

## ✅ Integration Checklist

When integrating this feature:

- [ ] Run database migration
- [ ] Copy all new files to correct locations
- [ ] Update admin/umrah.php with modal and scripts
- [ ] Update client/umrah.php with column and function
- [ ] Create uploads folder with 755 permissions
- [ ] Test admin upload functionality
- [ ] Test client view functionality
- [ ] Verify folder structure creation
- [ ] Check database updates
- [ ] Test file deletion
- [ ] Verify security measures
- [ ] Update team documentation

---

## 🐛 Debugging Integration

### Check Database Status
```sql
-- Verify columns exist
DESCRIBE umrah_bookings;

-- Check stored paths
SELECT booking_id, name, photo_path, passport_path 
FROM umrah_bookings 
WHERE photo_path IS NOT NULL OR passport_path IS NOT NULL;
```

### Check File System
```bash
# List uploaded files
find uploads -type f

# Check permissions
ls -la uploads/

# Check folder size
du -sh uploads/
```

### Check Browser Console
```javascript
// Open browser DevTools
// Check for JavaScript errors
// Verify API responses in Network tab
// Check SweetAlert alerts
```

### Check Server Logs
```bash
# PHP errors
tail -f /var/log/php-errors.log

# Web server errors
tail -f /var/log/apache2/error.log

# Application logs
grep -r "upload\|photo\|passport" /var/log/
```

---

## 📞 Support Points

For specific integration questions:

1. **Database**: See `migrations/add_photo_passport_storage.sql`
2. **Admin Interface**: See `admin/umrah.php` modifications
3. **Client Interface**: See `client/umrah.php` modifications
4. **APIs**: See `api/` folder files
5. **Documentation**: See `docs/` folder

---

**Last Updated**: February 4, 2026  
**Integration Version**: 1.0  
**Status**: Complete
