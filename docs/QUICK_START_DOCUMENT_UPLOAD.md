# Photo & Passport Upload - Quick Start Guide

## Installation (5 minutes)

### Step 1: Run Database Migration
```bash
cd /xampp/htdocs/almoqadas/mtravels
mysql -u root -p travelagency_saas < migrations/add_photo_passport_storage.sql
```

Or run the automatic setup script in your browser:
- Open: `http://localhost/almoqadas/mtravels/scripts/setup-document-upload.php`
- Click "Setup Complete"

### Step 2: Create Uploads Folder
Ensure the `uploads` folder exists and is writable:
```bash
mkdir -p uploads
chmod 755 uploads
```

### Step 3: Verify Files are in Place
Check that these files exist:
- ✓ `/api/upload_member_documents.php`
- ✓ `/api/get_member_documents.php`
- ✓ `/api/delete_member_document.php`
- ✓ `/js/member-document-upload.js`
- ✓ `/js/umrah/open_documents_modal.js`
- ✓ `/modals/umrah/member_documents_modal.php`
- ✓ `/css/document-upload.css`

## Admin Usage

### Upload Photos/Passports:
1. Navigate to **Admin > Umrah Management**
2. Find a family and click **View Members** (or Actions dropdown)
3. In the member row dropdown, click **Photo & Passport**
4. A modal opens with two sections:
   - **Photo**: Upload JPG, PNG, or GIF (max 5MB)
   - **Passport**: Upload JPG, PNG, GIF, or PDF (max 5MB)
5. Click the respective upload button
6. File is validated and saved

### View Uploaded Documents:
- In the modal, existing files show with:
  - Preview (image or PDF indicator)
  - View button
  - Delete button

### Delete Documents:
- Click the trash icon next to the document
- Confirm deletion
- File is removed from filesystem and database

## Client (Member) Usage

### View Family Member Documents:
1. Client logs in to their dashboard
2. Navigate to **Umrah > Family List**
3. Expand a family to view members
4. In the **Documents** column:
   - **Blue icon** = Photo available (click to view/download)
   - **Gray icon** = Passport available (click to view/download)

### Viewing Files:
- **Image files** (JPG, PNG, GIF):
  - Opens in modal with download button
- **PDF files**:
  - Opens in new browser tab
  - Can be printed or downloaded from browser

## Folder Structure (Auto-created)

```
uploads/
└── 1/                          (tenant_id)
    └── 1/                      (branch_id)
        └── umrah/
            └── 5/              (family_id)
                ├── photo_10_1706123456_a1b2c3d4.jpg
                ├── passport_10_1706123456_b2c3d4e5.pdf
                └── ...
```

Each file is stored with a unique name:
- Format: `{type}_{booking_id}_{timestamp}_{random}.{ext}`
- Prevents file overwrites
- Easy to identify and manage

## File Size & Type Limits

| Type | Max Size | Formats |
|------|----------|---------|
| Photo | 5 MB | JPG, PNG, GIF |
| Passport | 5 MB | JPG, PNG, GIF, PDF |

## Troubleshooting

### "Upload folder not writable" Error
```bash
# Fix permissions
chmod 755 /path/to/uploads
```

### Files uploaded but not visible to clients
- Check database: `SELECT photo_path, passport_path FROM umrah_bookings WHERE booking_id = 123;`
- Verify file exists in filesystem
- Check tenant_id and branch_id match in session

### "File size exceeds 5MB"
- Compress image using online tools or:
  ```bash
  # Linux/Mac
  convert input.jpg -resize 1920x1080 output.jpg
  ```

### Folder/File Permissions Issues (Windows)
- Ensure IIS/Apache user has read/write access to uploads folder
- Right-click folder > Properties > Security > Edit permissions

## Security Features

✓ **Tenant Isolation**: Files stored by tenant/branch  
✓ **Authentication**: Login required for all operations  
✓ **File Validation**: Type and size checked server-side  
✓ **Unique Filenames**: Prevents overwriting and attacks  
✓ **Access Control**: Clients see only their own documents  
✓ **.htaccess Protection**: Prevents execution of scripts in upload folder  

## Database Verification

### Check if columns exist:
```sql
SHOW COLUMNS FROM umrah_bookings LIKE '%photo%' OR LIKE '%passport%';
```

Should return 4 columns:
- photo_path
- passport_path
- photo_uploaded_at
- passport_uploaded_at

### View uploaded files:
```sql
SELECT booking_id, name, photo_path, passport_path 
FROM umrah_bookings 
WHERE photo_path IS NOT NULL OR passport_path IS NOT NULL;
```

## Performance Tips

1. **Optimize Images**: Compress before uploading (< 2 MB recommended)
2. **PDF Compression**: Reduce PDF file size if possible
3. **Batch Operations**: Upload documents in batches if handling many members
4. **Cleanup Old Files**: Periodically delete unused documents from uploads folder

## API Reference

### JavaScript Functions

```javascript
// Open document upload modal
openMemberDocumentsModal(bookingId, memberName);

// Upload file
uploadMemberDocumentModal('photo'); // or 'passport'

// Load existing documents
loadMemberDocumentsModal(bookingId);

// Delete document
deleteMemberDocument(bookingId, 'photo'); // or 'passport'

// Client-side: View document
viewClientDocument(filePath, 'Photo'); // or 'Passport'
```

## Common Tasks

### Clear old documents
```bash
# Remove documents older than 30 days
find /path/to/uploads -type f -mtime +30 -delete
```

### Backup uploads folder
```bash
tar -czf uploads-backup.tar.gz uploads/
```

### Monitor storage usage
```bash
du -sh uploads/
```

## Support

For detailed API documentation, see: `docs/PHOTO_PASSPORT_UPLOAD.md`

For database changes, see: `migrations/add_photo_passport_storage.sql`
