# File Preview Feature - Document Upload

## Overview

The document upload modal now includes **file preview** functionality that allows admins to see what they're uploading before confirmation.

## Preview Features

### Photo Preview
When selecting a photo (JPG, PNG, GIF):
- **Live Image Preview**: See the actual image displayed at up to 200px height
- **File Name**: Display of the selected file name
- **File Size**: Shows file size in MB
- **Hover Effect**: Image scales slightly on hover for visual feedback
- **Animation**: Smooth slide-in animation when preview appears

### Passport Preview
When selecting a passport:

#### For Image Files (JPG, PNG, GIF):
- **Image Preview**: Displays the actual image thumbnail
- **File Name**: Shows the selected file name
- **File Size**: Displays file size in MB
- **Hover Effect**: Interactive scaling on hover

#### For PDF Files:
- **PDF Indicator**: Shows PDF document icon with indicator
- **File Name**: Displays the selected file name
- **File Size**: Shows file size in MB
- **Visual Indicator**: Clear indication that it's a PDF document

## How to Use

### Uploading with Preview

1. **Open Modal**: Go to Admin > Umrah > Select Member > Photo & Passport action
2. **Select File**: Click the file input to choose a document
3. **View Preview**: After selection, preview appears above the upload button
4. **Review**: Check file name, size, and content
5. **Upload**: Click "Upload Photo" or "Upload Passport" button
6. **Confirm**: Upload proceeds with validation

### Preview Sections

#### Photo Section
```
┌─────────────────────────────────┐
│     [Photo Preview Area]        │
│    [Image display if selected]  │
├─────────────────────────────────┤
│ File: passport.jpg              │
│ Size: 2.45 MB                   │
├─────────────────────────────────┤
│ Choose File Button              │
├─────────────────────────────────┤
│ Upload Photo Button             │
└─────────────────────────────────┘
```

#### Passport Section
```
┌─────────────────────────────────┐
│  [Passport Preview Area]        │
│  [Image or PDF indicator]       │
├─────────────────────────────────┤
│ File: documents.pdf             │
│ Size: 3.12 MB                   │
├─────────────────────────────────┤
│ Choose File Button              │
├─────────────────────────────────┤
│ Upload Passport Button          │
└─────────────────────────────────┘
```

## Technical Details

### JavaScript Functions

#### `previewPhotoBeforeUpload(event)`
Handles photo preview display
- **Trigger**: File input change event
- **Parameters**: File select event
- **Actions**:
  - Reads file using FileReader API
  - Displays image as data URL
  - Shows file name and size
  - Animates preview appearance

#### `previewPassportBeforeUpload(event)`
Handles passport preview display
- **Trigger**: File input change event
- **Parameters**: File select event
- **Actions**:
  - Detects file type (image or PDF)
  - For images: displays thumbnail
  - For PDFs: shows PDF indicator
  - Shows file name and size
  - Animates preview appearance

### CSS Classes

#### Animation
```css
@keyframes slideIn {
    from { opacity: 0; transform: translateY(-10px); }
    to { opacity: 1; transform: translateY(0); }
}
```

#### Preview Container
```css
#photoPreviewBeforeUpload,
#passportPreviewBeforeUpload {
    padding: 15px;
    background-color: #f8f9fa;
    border: 2px solid #dee2e6;
    border-radius: 8px;
    animation: slideIn 0.3s ease-in-out;
}
```

#### Image Styling
```css
#photoPreviewImage {
    border: 1px solid #dee2e6;
    box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
    transition: transform 0.2s ease;
}

#photoPreviewImage:hover {
    transform: scale(1.02);
}
```

## File Size Display

File sizes are calculated and displayed in MB:
```javascript
const fileSize = (file.size / 1024 / 1024).toFixed(2) + ' MB';
```

Examples:
- 1.5 MB file shows as "1.50 MB"
- 500 KB file shows as "0.49 MB"
- 2.8 MB file shows as "2.80 MB"

## File Type Detection

### Photo Files
- Automatically detects: JPG, JPEG, PNG, GIF
- Shows actual image preview

### Passport Files
- **Images**: JPG, JPEG, PNG, GIF - shows thumbnail
- **PDFs**: Shows PDF document indicator with icon

### Detection Method
```javascript
const fileExt = file.name.split('.').pop().toLowerCase();

if (fileExt === 'pdf') {
    // Show PDF indicator
} else if (['jpg', 'jpeg', 'png', 'gif'].includes(fileExt)) {
    // Show image preview
}
```

## Browser Compatibility

The preview feature uses:
- **FileReader API**: Supported in all modern browsers
- **Data URLs**: All browsers
- **CSS Animations**: All modern browsers
- **JavaScript ES6**: Chrome 90+, Firefox 88+, Safari 14+, Edge 90+

**Fallback**: If preview fails to load, upload still works normally.

## Performance Considerations

### Memory Usage
- Images are converted to data URLs (in memory)
- Large images may cause slight memory overhead
- Recommended: Images under 5 MB (already enforced by server)

### Loading Speed
- FileReader is asynchronous (non-blocking)
- Large files may take 1-2 seconds to preview
- UI remains responsive

### Optimization
- Preview is only created for user-selected files
- Preview is cleared when modal is closed
- Only one preview active at a time per section

## Troubleshooting

### Preview Not Showing
1. **Verify file selection**: Click file input to select
2. **Check file type**: Ensure it's JPG, PNG, GIF, or PDF
3. **Clear cache**: Browser cache may have stale JS
4. **Check console**: Open browser console for errors

### Preview Shows Blank
1. **File may be corrupted**: Try another file
2. **Large file**: May take longer to load
3. **Browser issue**: Try another browser

### PDF Shows as Image
1. **File extension mismatch**: Rename to .pdf
2. **PDF actually image**: Legitimate behavior

### File Size Shows as 0
1. **Rare edge case**: Refresh page and try again
2. **Browser issue**: Try another browser

## Best Practices

### For Admins
1. **Always check preview**: Verify file before upload
2. **Check file size**: Ensure it meets limits
3. **Use high-quality photos**: Better for members
4. **Compress if needed**: Use online tools
5. **Use valid PDFs**: Avoid corrupted files

### For Development
1. **Test with various file types**
2. **Test with large files** (close to 5MB limit)
3. **Test on different browsers**
4. **Monitor console** for errors
5. **Check performance** with many uploads

## Future Enhancements

Possible improvements:
- [ ] Crop/rotate images before upload
- [ ] PDF thumbnail preview
- [ ] File compression option
- [ ] Multiple file preview
- [ ] Drag and drop with preview
- [ ] EXIF data display for photos
- [ ] OCR preview for documents
- [ ] Image quality indicator

## Related Files

- **Modal**: `modals/umrah/member_documents_modal.php`
- **Styling**: `css/document-upload.css`
- **Upload API**: `api/upload_member_documents.php`
- **JavaScript**: Embedded in modal file

## Support

For issues with the preview feature:
1. Check browser console (F12)
2. Verify file format and size
3. Try different browser
4. Check FileReader API support
5. Review this documentation

---

**Feature Added**: February 4, 2026  
**Status**: ✅ Complete  
**Browser Support**: All modern browsers
