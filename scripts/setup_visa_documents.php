<?php
/**
 * Visa Documents Feature Setup Script
 * Verifies installation and creates necessary directories
 */

set_time_limit(0);

echo "=== Visa Documents Setup Verification ===\n\n";

$errors = [];
$warnings = [];
$success = [];

// Check PHP version
if (version_compare(PHP_VERSION, '7.4.0', '<')) {
    $errors[] = "PHP 7.4+ required, current: " . PHP_VERSION;
} else {
    $success[] = "PHP version: " . PHP_VERSION;
}

// Check for required PHP extensions
$requiredExtensions = ['pdo', 'pdo_mysql', 'json'];
foreach ($requiredExtensions as $ext) {
    if (!extension_loaded($ext)) {
        $errors[] = "Missing PHP extension: $ext";
    } else {
        $success[] = "PHP extension loaded: $ext";
    }
}

// Check database connection
try {
    require_once '../includes/db.php';
    $success[] = "Database connection successful";
} catch (Exception $e) {
    $errors[] = "Database connection failed: " . $e->getMessage();
}

// Check file structure
$requiredFiles = [
    'modals/visa/documents_modal.php',
    'api/visa_documents_upload.php',
    'api/visa_document_types.php',
    'js/visa/document_manager.js',
    'migrations/add_visa_documents_tables.sql',
    'includes/language/visa_documents_en.php'
];

foreach ($requiredFiles as $file) {
    $filePath = __DIR__ . '/../' . $file;
    if (file_exists($filePath)) {
        $success[] = "File exists: $file";
    } else {
        $errors[] = "Missing file: $file";
    }
}

// Check uploads directory
$uploadsDir = __DIR__ . '/../uploads/visas';
if (!is_dir($uploadsDir)) {
    if (@mkdir($uploadsDir, 0755, true)) {
        $success[] = "Created uploads directory: uploads/visas";
    } else {
        $warnings[] = "Could not create uploads directory, may need manual creation";
    }
} else {
    $success[] = "Uploads directory exists: uploads/visas";
}

// Check directory permissions
if (is_dir($uploadsDir)) {
    if (is_writable($uploadsDir)) {
        $success[] = "Uploads directory is writable";
    } else {
        $warnings[] = "Uploads directory is not writable, may need permission adjustment";
    }
}

// Check if tables exist in database
try {
    if (isset($pdo)) {
        $stmt = $pdo->query("SHOW TABLES LIKE 'visa_document%'");
        $tables = $stmt->fetchAll();
        
        if (count($tables) >= 2) {
            $success[] = "Database tables found: visa_document_types, visa_documents";
        } else {
            $warnings[] = "Database tables not found, run migration: sql/add_visa_documents_tables.sql";
        }
    }
} catch (Exception $e) {
    $warnings[] = "Could not check database tables: " . $e->getMessage();
}

// Display results
echo "=== SUCCESS ===\n";
foreach ($success as $msg) {
    echo "✓ $msg\n";
}

if (!empty($warnings)) {
    echo "\n=== WARNINGS ===\n";
    foreach ($warnings as $msg) {
        echo "⚠ $msg\n";
    }
}

if (!empty($errors)) {
    echo "\n=== ERRORS ===\n";
    foreach ($errors as $msg) {
        echo "✗ $msg\n";
    }
    echo "\n❌ Setup verification FAILED\n";
    exit(1);
} else {
    echo "\n✅ Setup verification PASSED\n";
    echo "\nNext steps:\n";
    echo "1. Run the database migration if not done: migrations/add_visa_documents_tables.sql\n";
    echo "2. Add translations to your language files\n";
    echo "3. Test the feature by uploading a document\n";
    exit(0);
}
?>
