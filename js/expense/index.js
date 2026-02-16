// Expense Management - Main JavaScript File
// Loads all expense management scripts in the correct order

// Load external libraries first
// Chart.js is loaded via CDN in the PHP file
// xlsx library is loaded via CDN in the PHP file

// Load our custom scripts in order of dependencies
// 1. File input handler (no dependencies)
// 2. Button protection (depends on jQuery)
// 3. Chart functions and utilities (depends on Chart.js)
// 4. Event handlers (depends on all above + jQuery)

// Note: The PHP file will set allowedFeatures variable before loading this file
