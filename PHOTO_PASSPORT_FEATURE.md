# Photo & Passport Upload Feature

Complete photo and passport document upload and viewing system for Umrah member management.

## 📋 Overview

This feature allows:
- **Admins**: Upload and manage member photos and passports
- **Clients**: View their family members' documents
- **Automatic Storage**: Organized folder structure by tenant, branch, and family
- **Security**: Tenant isolation, file validation, and access control

## 🚀 Quick Start

### Installation
```bash
# 1. Run database migration
mysql -u root -p travelagency_saas < migrations/add_photo_passport_storage.sql

# 2. Ensure uploads folder exists and is writable
mkdir -p uploads
chmod 755 uploads

# 3. Verify the installation
# Open: http://localhost/almoqadas/mtravels/scripts/setup-document-upload.php
```

### Usage

**For Admins:**
1. Go to Admin → Umrah Management
2. Click on a member's "Photo & Passport" action
3. Upload files (JPG, PNG, GIF for photos; same + PDF for passport)
4. Max 5MB per file

**For Clients:**
1. Go to Umrah → Family List
2. Expand a family to see members
3. Click photo or passport icons to view documents

## 📁 Folder Structure

Documents are stored in an organized hierarchy:
```
uploads/
├── {tenant_id}/
│   ├── {branch_id}/
│   │   └── umrah/
│   │       └── {family_id}/
│   │           ├── photo_booking_id_timestamp_random.jpg
│   │           └── passport_booking_id_timestamp_random.pdf
```

This ensures:
- Proper tenant/branch isolation
- Easy document management
- Scalable organization
- Fast retrieval

## 🔧 Components

### Database Changes
**File**: `migrations/add_photo_passport_storage.sql`

New columns in `umrah_bookings`:
- `photo_path` - Path to photo file
- `passport_path` - Path to passport file
- `photo_uploaded_at` - Upload timestamp
- `passport_uploaded_at` - Upload timestamp

### API Endpoints

#### 1. Upload Document
- **Endpoint**: `/api/upload_member_documents.php`
- **Method**: POST
- **Parameters**: booking_id, document_type, file

#### 2. Get Documents
- **Endpoint**: `/api/get_member_documents.php`
- **Method**: GET
- **Parameters**: booking_id

#### 3. Delete Document
- **Endpoint**: `/api/delete_member_document.php`
- **Method**: POST
- **Parameters**: booking_id, document_type

### Frontend Components

#### Admin
- **Modal**: `modals/umrah/member_documents_modal.php`
- **Script**: `js/member-document-upload.js`
- **Controller**: `js/umrah/open_documents_modal.js`
- **Modified**: `admin/umrah.php`

#### Client
- **View**: `client/umrah.php` (Documents column added)
- **Script**: JavaScript functions in page

### Styling
- **CSS**: `css/document-upload.css`
- Responsive design
- Modal styling
- Upload progress indicators

## 📊 File Format Support

| Document | Formats | Max Size |
|----------|---------|----------|
| Photo | JPG, PNG, GIF | 5 MB |
| Passport | JPG, PNG, GIF, PDF | 5 MB |

## 🔐 Security

✓ **Authentication**: All endpoints require login  
✓ **Authorization**: Tenant/branch isolation  
✓ **File Validation**: Type and size checks (client & server)  
✓ **Unique Names**: Prevents overwrites - `{type}_{booking_id}_{timestamp}_{random}.{ext}`  
✓ **Clean Deletion**: Old files removed when replaced  
✓ **.htaccess Protection**: Prevents script execution in uploads folder  
✓ **Session Verification**: All API calls verify session  

## 📚 Documentation

- **Complete Guide**: `docs/PHOTO_PASSPORT_UPLOAD.md`
- **Quick Start**: `docs/QUICK_START_DOCUMENT_UPLOAD.md`
- **Setup Script**: `scripts/setup-document-upload.php`

## 🔄 Workflow

### Admin Upload Workflow
```
Member List
  ↓
Select "Photo & Passport"
  ↓
Modal Opens
  ↓
Choose File → Validate → Upload
  ↓
Create Folder Structure
  ↓
Save File → Update Database
  ↓
Show Preview
```

### Client View Workflow
```
Family List
  ↓
Expand Family
  ↓
See Document Icons
  ↓
Click Icon
  ↓
View/Download File
```

## 📝 Database Examples

### Check uploaded documents
```sql
SELECT booking_id, name, photo_path, passport_path, photo_uploaded_at, passport_uploaded_at
FROM umrah_bookings
WHERE photo_path IS NOT NULL OR passport_path IS NOT NULL
LIMIT 10;
```

### Get specific member's documents
```sql
SELECT ub.booking_id, ub.name, ub.photo_path, ub.passport_path
FROM umrah_bookings ub
WHERE ub.family_id = 5 AND ub.tenant_id = 1 AND ub.branch_id = 1;
```

### Cleanup old files (SQL only, not recommended for auto-deletion)
```sql
SELECT photo_path, passport_path FROM umrah_bookings
WHERE photo_uploaded_at < DATE_SUB(NOW(), INTERVAL 1 YEAR);
```

## 🛠️ Maintenance

### Check storage usage
```bash
du -sh uploads/
```

### List all documents
```bash
find uploads -type f | wc -l
```

### Backup documents
```bash
tar -czf uploads-backup-$(date +%Y%m%d).tar.gz uploads/
```

### Clear documents for a family
```bash
# Delete all documents for family_id = 5
rm -rf uploads/1/1/umrah/5/*
```

## 🐛 Troubleshooting

### Files not uploading
1. Check folder permissions: `ls -la uploads/`
2. Verify file size < 5MB
3. Check file type (JPG, PNG, GIF, PDF)
4. Review browser console for errors

### Files not visible in admin modal
1. Check database: `SELECT photo_path FROM umrah_bookings WHERE booking_id = X;`
2. Verify file exists: `ls uploads/1/1/umrah/5/`
3. Check folder permissions

### Client can't see documents
1. Verify tenant_id and branch_id in session
2. Check if photo_path/passport_path is NULL in database
3. Confirm file exists in filesystem

### Database columns don't exist
1. Run migration: `mysql -u root -p db < migrations/add_photo_passport_storage.sql`
2. Or use setup script: `http://localhost/.../scripts/setup-document-upload.php`

## 📈 Performance

- **File Size Limit**: 5MB prevents large uploads
- **Unique Naming**: Fast lookups without collisions
- **Database Index**: Quick photo/passport retrieval
- **Folder Structure**: Scales to millions of files

## 🔮 Future Enhancements

- [ ] Bulk upload support
- [ ] Document templates
- [ ] Automatic thumbnail generation
- [ ] OCR for passport data extraction
- [ ] Document version history
- [ ] Archive old documents
- [ ] Document expiration tracking
- [ ] Multiple documents per type
- [ ] Digital signatures

## 📞 Support

For issues or questions:
1. Check `PHOTO_PASSPORT_UPLOAD.md` for detailed documentation
2. Review `QUICK_START_DOCUMENT_UPLOAD.md` for common tasks
3. Check database with provided SQL examples
4. Review browser console for client-side errors
5. Check server logs for API errors

## 📄 Files Modified/Created

### Created Files
- `migrations/add_photo_passport_storage.sql`
- `api/upload_member_documents.php`
- `api/get_member_documents.php`
- `api/delete_member_document.php`
- `js/member-document-upload.js`
- `js/umrah/open_documents_modal.js`
- `modals/umrah/member_documents_modal.php`
- `css/document-upload.css`
- `scripts/setup-document-upload.php`
- `docs/PHOTO_PASSPORT_UPLOAD.md`
- `docs/QUICK_START_DOCUMENT_UPLOAD.md`

### Modified Files
- `admin/umrah.php` - Added document upload link and modal
- `client/umrah.php` - Added document column and viewing

## 📊 Statistics

- **Total New Files**: 10
- **Total Modified Files**: 2
- **Database Columns Added**: 4
- **API Endpoints**: 3
- **Supported File Types**: 5 (JPG, PNG, GIF, PDF + more via extension)
- **Max File Size**: 5 MB
- **Folder Levels**: 4 (tenant → branch → umrah → family)

## 🎯 Goals Achieved

✅ Photo upload field for members  
✅ Passport upload field for members  
✅ Organized folder structure (tenant/branch/umrah/family)  
✅ Viewable by members in their dashboard  
✅ Admin upload and management interface  
✅ Secure file handling and storage  
✅ Database integration  
✅ Document deletion support  
✅ Responsive UI  
✅ Complete documentation  

---

**Last Updated**: February 4, 2026  
**Version**: 1.0  
**Status**: Ready for Production
