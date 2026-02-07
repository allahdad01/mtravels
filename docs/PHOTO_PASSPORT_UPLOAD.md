# Photo and Passport Upload Feature - Documentation

## Overview
This feature allows admins to upload member photos and passports for Umrah bookings. Documents are stored in an organized folder structure and are viewable to members (clients).

## Folder Structure
Documents are stored with the following structure:
```
uploads/
├── {tenant_id}/
│   ├── {branch_id}/
│   │   └── umrah/
│   │       └── {family_id}/
│   │           ├── photo_booking_id_timestamp_random.jpg
│   │           ├── photo_booking_id_timestamp_random.png
│   │           ├── passport_booking_id_timestamp_random.pdf
│   │           └── passport_booking_id_timestamp_random.jpg
```

This ensures proper isolation by tenant and branch.

## Database Changes

### Added Columns to `umrah_bookings` Table:
- `photo_path` (VARCHAR 500) - Relative path to photo file
- `passport_path` (VARCHAR 500) - Relative path to passport file
- `photo_uploaded_at` (TIMESTAMP) - When photo was uploaded
- `passport_uploaded_at` (TIMESTAMP) - When passport was uploaded

### Migration File:
Location: `migrations/add_photo_passport_storage.sql`

Run migration:
```sql
mysql -u username -p database_name < migrations/add_photo_passport_storage.sql
```

## Admin Interface

### Accessing the Feature:
1. Go to Admin > Umrah Management
2. In the members table dropdown actions, click "Photo & Passport"
3. A modal will open showing:
   - Photo upload section
   - Passport upload section
   - Preview of existing files
   - Delete options

### Supported File Types:
- **Photo**: JPG, PNG, GIF (Max 5MB)
- **Passport**: JPG, PNG, GIF, PDF (Max 5MB)

### Upload Process:
1. Click file input and select a file
2. Click "Upload Photo" or "Upload Passport" button
3. System validates:
   - File size (max 5MB)
   - File type (allowed formats only)
4. Creates folder structure if needed
5. Saves file with unique naming: `{type}_{booking_id}_{timestamp}_{random}.{ext}`
6. Updates database with file path
7. Previous file is automatically deleted

## Client View

### Member Details Tab:
Clients can see their family members with a "Documents" column showing:
- **Photo icon** (blue) - Click to view photo
- **Passport icon** (gray) - Click to view passport
- Files are only displayed if they exist

### Document Viewing:
- **Images**: Open in SweetAlert modal with download option
- **PDFs**: Open in new browser tab
- **Other files**: Direct download

## API Endpoints

### 1. Upload Document
**Endpoint**: `/api/upload_member_documents.php`
**Method**: POST
**Content-Type**: multipart/form-data

**Parameters**:
- `booking_id` (int) - Member booking ID
- `document_type` (string) - 'photo' or 'passport'
- `file` (file) - The document file

**Response**:
```json
{
  "success": true,
  "message": "Photo uploaded successfully",
  "file_path": "/uploads/1/1/umrah/5/photo_123_1706123456_a1b2c3d4.jpg",
  "file_name": "photo_123_1706123456_a1b2c3d4.jpg"
}
```

### 2. Get Member Documents
**Endpoint**: `/api/get_member_documents.php`
**Method**: GET

**Parameters**:
- `booking_id` (int) - Member booking ID

**Response**:
```json
{
  "success": true,
  "photo_path": "/uploads/1/1/umrah/5/photo_123_1706123456_a1b2c3d4.jpg",
  "passport_path": "/uploads/1/1/umrah/5/passport_123_1706123456_a1b2c3d4.pdf"
}
```

### 3. Delete Document
**Endpoint**: `/api/delete_member_document.php`
**Method**: POST

**Parameters**:
- `booking_id` (int) - Member booking ID
- `document_type` (string) - 'photo' or 'passport'

**Response**:
```json
{
  "success": true,
  "message": "Photo deleted successfully"
}
```

## Files Modified/Created

### New Files:
1. `migrations/add_photo_passport_storage.sql` - Database migration
2. `api/upload_member_documents.php` - Upload handler
3. `api/get_member_documents.php` - Fetch documents
4. `api/delete_member_document.php` - Delete handler
5. `js/member-document-upload.js` - Upload functionality
6. `js/umrah/open_documents_modal.js` - Modal controller
7. `modals/umrah/member_documents_modal.php` - Upload/view modal

### Modified Files:
1. `admin/umrah.php` - Added document upload link and modal inclusion
2. `client/umrah.php` - Added document column and viewing functionality

## Security Features

1. **Authentication Check**: All APIs verify user session
2. **Tenant/Branch Isolation**: Files only accessible within user's tenant/branch
3. **File Type Validation**: Both client-side and server-side validation
4. **File Size Limit**: Maximum 5MB per file
5. **Unique Naming**: Prevents file overwrite attacks
6. **Secure Deletion**: Old files deleted when replaced
7. **Access Control**: Clients only see their own family documents

## Usage Examples

### Admin Upload Flow:
```javascript
// Click action menu -> "Photo & Passport"
openMemberDocumentsModal(bookingId, memberName);

// In modal, select file and click upload
uploadMemberDocumentModal('photo');

// System uploads and updates database
// Modal refreshes to show uploaded file
```

### Client Viewing Flow:
```javascript
// In family members table
// Click photo icon to view
viewClientDocument(filePath, 'Photo');

// Click passport icon to view/download
viewClientDocument(filePath, 'Passport');
```

## Troubleshooting

### Files Not Uploading:
1. Check folder permissions (ensure `uploads/` is writable)
2. Verify file size (max 5MB)
3. Check file type (JPG, PNG, GIF for photos; same + PDF for passport)
4. Review browser console for errors

### Files Not Visible to Clients:
1. Verify file_path in database is correct
2. Check folder structure is created properly
3. Ensure file exists in filesystem

### Database Issues:
1. Run migration script
2. Verify new columns exist: `SHOW COLUMNS FROM umrah_bookings;`
3. Check tenant_id and branch_id match in session

## Performance Considerations

1. **Folder Structure**: Limits to ~65k files per family folder
2. **File Naming**: Includes timestamp for uniqueness
3. **Database Index**: Index on photo_path, passport_path for quick lookup
4. **File Size**: 5MB limit prevents excessive storage

## Future Enhancements

- [ ] Bulk upload support
- [ ] Document templates/templates
- [ ] Automatic document generation
- [ ] OCR for passport data extraction
- [ ] Archive old documents
- [ ] Document expiration tracking
- [ ] Multiple photos/passports per member
- [ ] Document signatures verification
