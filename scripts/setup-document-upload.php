<?php
/**
 * Setup script for Photo/Passport upload feature
 * Run this once to initialize the database tables and folder structures
 */

// Start session
session_start();

// Check if admin
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    die('Access denied. Admin access required.');
}

require_once '../config.php';
require_once '../includes/db.php';

$setup_log = [];
$errors = [];

// Step 1: Check if columns exist
try {
    $checkSql = "SHOW COLUMNS FROM umrah_bookings WHERE Field IN ('photo_path', 'passport_path', 'photo_uploaded_at', 'passport_uploaded_at')";
    $stmt = $pdo->query($checkSql);
    $existing_columns = $stmt->fetchAll(PDO::FETCH_COLUMN);
    
    if (count($existing_columns) === 4) {
        $setup_log[] = "✓ All required columns already exist";
    } else {
        $setup_log[] = "! Some columns missing, attempting to add...";
        
        // Add columns
        $columns_to_add = [
            "photo_path" => "ALTER TABLE umrah_bookings ADD COLUMN photo_path VARCHAR(500) DEFAULT NULL AFTER remarks",
            "passport_path" => "ALTER TABLE umrah_bookings ADD COLUMN passport_path VARCHAR(500) DEFAULT NULL AFTER photo_path",
            "photo_uploaded_at" => "ALTER TABLE umrah_bookings ADD COLUMN photo_uploaded_at TIMESTAMP NULL AFTER passport_path",
            "passport_uploaded_at" => "ALTER TABLE umrah_bookings ADD COLUMN passport_uploaded_at TIMESTAMP NULL AFTER photo_uploaded_at"
        ];
        
        foreach ($columns_to_add as $col_name => $sql) {
            try {
                // Check if column exists before adding
                $check = "SHOW COLUMNS FROM umrah_bookings WHERE Field = '$col_name'";
                $result = $pdo->query($check)->fetchAll();
                
                if (empty($result)) {
                    $pdo->exec($sql);
                    $setup_log[] = "✓ Added column: $col_name";
                }
            } catch (Exception $e) {
                $errors[] = "! Column $col_name: " . $e->getMessage();
            }
        }
        
        // Add index
        try {
            $pdo->exec("CREATE INDEX IF NOT EXISTS idx_photo_passport ON umrah_bookings(photo_path, passport_path)");
            $setup_log[] = "✓ Created index: idx_photo_passport";
        } catch (Exception $e) {
            $setup_log[] = "! Index may already exist: " . $e->getMessage();
        }
    }
} catch (Exception $e) {
    $errors[] = "Database check failed: " . $e->getMessage();
}

// Step 2: Create folder structure
$base_upload = __DIR__ . '/../uploads';

if (!is_dir($base_upload)) {
    if (mkdir($base_upload, 0755, true)) {
        $setup_log[] = "✓ Created base upload folder: uploads/";
    } else {
        $errors[] = "Failed to create uploads folder";
    }
} else {
    $setup_log[] = "✓ Uploads folder exists";
}

// Step 3: Set folder permissions
if (is_dir($base_upload)) {
    if (@chmod($base_upload, 0755)) {
        $setup_log[] = "✓ Set proper permissions on uploads folder";
    } else {
        $setup_log[] = "! Could not set folder permissions (may require manual setup)";
    }
}

// Step 4: Create .htaccess for security
$htaccess_content = <<<'HTACCESS'
# Allow image and PDF viewing
<FilesMatch "\.(jpg|jpeg|png|gif|pdf)$">
    Allow from all
</FilesMatch>

# Deny direct access to other files
<FilesMatch "\.php$">
    Deny from all
</FilesMatch>

# Prevent directory listing
Options -Indexes
HTACCESS;

$htaccess_path = $base_upload . '/.htaccess';
if (@file_put_contents($htaccess_path, $htaccess_content)) {
    $setup_log[] = "✓ Created .htaccess for security";
} else {
    $setup_log[] = "! Could not create .htaccess (optional)";
}

?>
<!DOCTYPE html>
<html>
<head>
    <title>Document Upload Setup</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        .setup-container {
            max-width: 600px;
            margin-top: 50px;
        }
        .log-item {
            padding: 10px;
            margin: 5px 0;
            border-left: 4px solid #007bff;
            background-color: #f8f9fa;
        }
        .log-item.success {
            border-left-color: #28a745;
            background-color: #f0fff4;
        }
        .log-item.error {
            border-left-color: #dc3545;
            background-color: #fff5f5;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="setup-container">
            <h1 class="mb-4">Document Upload Setup</h1>
            
            <div class="alert alert-info">
                <strong>Setup Progress</strong>
                <p>Running initialization for Photo/Passport upload feature...</p>
            </div>
            
            <h5 class="mt-4 mb-3">Setup Log:</h5>
            <div class="setup-log">
                <?php foreach ($setup_log as $log): ?>
                    <div class="log-item success">
                        <?= htmlspecialchars($log) ?>
                    </div>
                <?php endforeach; ?>
                
                <?php foreach ($errors as $error): ?>
                    <div class="log-item error">
                        <strong>Error:</strong> <?= htmlspecialchars($error) ?>
                    </div>
                <?php endforeach; ?>
            </div>
            
            <?php if (empty($errors)): ?>
                <div class="alert alert-success mt-4">
                    <strong>Setup Complete!</strong> The Photo/Passport upload feature is ready to use.
                    <br><br>
                    <a href="../admin/umrah.php" class="btn btn-primary btn-sm">Go to Umrah Management</a>
                </div>
            <?php else: ?>
                <div class="alert alert-warning mt-4">
                    <strong>Setup Completed with Warnings</strong>
                    <p>Please review the errors above and ensure all requirements are met.</p>
                </div>
            <?php endif; ?>
        </div>
    </div>
</body>
</html>
