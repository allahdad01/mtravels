# Expense Management JavaScript Files

This directory contains all JavaScript files for the expense management system, extracted from the inline scripts in `admin/expense_management.php`.

## File Structure

### Main Files

1. **`index.js`** - Main entry point that documents the load order
2. **`file_input_handler.js`** - Handles custom file input labels
3. **`button_protection.js`** - Button protection and state management
4. **`expense_management.js`** - Core functionality (charts, utilities, data loading)
5. **`event_handlers.js`** - jQuery event handlers and form functionality

### Load Order

The files are loaded in the following order in `admin/expense_management.php`:

```html
<!-- Set allowedFeatures before loading scripts -->
<script>
    var allowedFeatures = <?= json_encode($allowed_features); ?>;
</script>
<script src="../js/expense/file_input_handler.js"></script>
<script src="../js/expense/button_protection.js"></script>
<script src="../js/expense/expense_management.js"></script>
<script src="../js/expense/event_handlers.js"></script>
```

## Dependencies

- **jQuery** - Required by all files
- **Chart.js** - Required by `expense_management.js` (loaded via CDN)
- **xlsx library** - Required for Excel export functionality (loaded via CDN)

## Functionality Overview

### file_input_handler.js
- Custom file input label updates
- Updates label text when files are selected

### button_protection.js
- Button state management during AJAX operations
- Protection against double submissions
- Loading states for all form buttons
- Error handling to restore button states

### expense_management.js
- Chart creation and management (income, expense, profit/loss)
- Data loading and processing
- Export functionality (charts, Excel reports)
- Animation utilities for amount updates
- Profit/loss card styling

### event_handlers.js
- Form submission handlers
- Modal management
- Date range filtering
- Quick date selection
- AJAX calls to backend
- URL parameter handling

## Notes

- All PHP localization strings have been removed from the JavaScript files
- The `allowedFeatures` variable is set globally before loading scripts
- External CDN dependencies (Chart.js, xlsx) are still loaded in the PHP file
- All functionality remains identical to the original inline implementation

## Maintenance

When updating functionality:
1. Modify the appropriate separate JavaScript file
2. Test all related functionality
3. Ensure proper load order is maintained
4. Update this README if adding new files or changing load order