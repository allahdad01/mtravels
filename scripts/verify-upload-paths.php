<?php
/**
 * Verify Upload Paths - Debug Script
 * Shows the paths stored in database and whether files exist
 */

session_start();

// Check if admin
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    die('Access denied. Admin access required.');
}

require_once '../config.php';
require_once '../includes/db.php';

$tenant_id = $_SESSION['tenant_id'];
$branch_id = $_SESSION['branch_id'];

?>
<!DOCTYPE html>
<html>
<head>
    <title>Upload Path Verification</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        .path-item { padding: 10px; margin: 5px 0; border-left: 4px solid #007bff; background: #f8f9fa; }
        .path-item.found { border-left-color: #28a745; background: #f0fff4; }
        .path-item.missing { border-left-color: #dc3545; background: #fff5f5; }
        .path-item.url { border-left-color: #0c5ff4; }
        code { background: #f4f4f4; padding: 2px 5px; border-radius: 3px; }
    </style>
</head>
<body>
    <div class="container mt-4">
        <h1>Upload Path Verification</h1>
        
        <div class="alert alert-info">
            <strong>Tenant ID:</strong> <?= $tenant_id ?> | 
            <strong>Branch ID:</strong> <?= $branch_id ?>
        </div>

        <h3 class="mt-4">Uploaded Files</h3>
        
        <?php
        $sql = "SELECT booking_id, name, photo_path, passport_path, photo_uploaded_at, passport_uploaded_at 
                FROM umrah_bookings 
                WHERE (photo_path IS NOT NULL OR passport_path IS NOT NULL) 
                AND tenant_id = ? AND branch_id = ?
                LIMIT 20";
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$tenant_id, $branch_id]);
        $results = $stmt->fetchAll(PDO::FETCH_ASSOC);

        if (empty($results)) {
            echo '<div class="alert alert-warning">No files uploaded yet.</div>';
        } else {
            foreach ($results as $row) {
                echo '<div class="card mb-3">';
                echo '<div class="card-header"><strong>' . htmlspecialchars($row['name']) . '</strong> (Booking ID: ' . $row['booking_id'] . ')</div>';
                echo '<div class="card-body">';

                // Photo
                if (!empty($row['photo_path'])) {
                    $photo_file = __DIR__ . '/..' . $row['photo_path'];
                    $photo_exists = file_exists($photo_file);
                    
                    echo '<div class="path-item ' . ($photo_exists ? 'found' : 'missing') . '">';
                    echo '<strong>Photo:</strong><br>';
                    echo '<code>' . htmlspecialchars($row['photo_path']) . '</code><br>';
                    echo '<small class="text-muted">Uploaded: ' . $row['photo_uploaded_at'] . '</small><br>';
                    echo $photo_exists ? '✅ <span class="text-success">File exists</span>' : '❌ <span class="text-danger">File missing</span>';
                    
                    // Test link
                    if ($photo_exists) {
                        echo ' | <a href="' . htmlspecialchars($row['photo_path']) . '" target="_blank" class="ms-2">Test Link</a>';
                    }
                    
                    echo '</div>';
                }

                // Passport
                if (!empty($row['passport_path'])) {
                    $passport_file = __DIR__ . '/..' . $row['passport_path'];
                    $passport_exists = file_exists($passport_file);
                    
                    echo '<div class="path-item ' . ($passport_exists ? 'found' : 'missing') . '">';
                    echo '<strong>Passport:</strong><br>';
                    echo '<code>' . htmlspecialchars($row['passport_path']) . '</code><br>';
                    echo '<small class="text-muted">Uploaded: ' . $row['passport_uploaded_at'] . '</small><br>';
                    echo $passport_exists ? '✅ <span class="text-success">File exists</span>' : '❌ <span class="text-danger">File missing</span>';
                    
                    // Test link
                    if ($passport_exists) {
                        echo ' | <a href="' . htmlspecialchars($row['passport_path']) . '" target="_blank" class="ms-2">Test Link</a>';
                    }
                    
                    echo '</div>';
                }

                echo '</div></div>';
            }
        }
        ?>

        <h3 class="mt-4">File System Check</h3>
        
        <?php
        $base_dir = __DIR__ . '/../uploads/' . $tenant_id . '/' . $branch_id . '/umrah';
        
        if (is_dir($base_dir)) {
            echo '<div class="alert alert-success">✅ Upload folder structure exists</div>';
            
            echo '<h5>Folder Contents:</h5>';
            echo '<pre>';
            
            $iterator = new RecursiveIteratorIterator(
                new RecursiveDirectoryIterator($base_dir),
                RecursiveIteratorIterator::SELF_FIRST
            );
            
            $file_count = 0;
            foreach ($iterator as $file) {
                if ($file->isFile()) {
                    $file_count++;
                    $relative = str_replace($base_dir, '', $file->getPathname());
                    $size = filesize($file->getPathname()) / 1024; // KB
                    echo htmlspecialchars($relative) . ' (' . round($size, 2) . ' KB)' . "\n";
                }
            }
            
            if ($file_count === 0) {
                echo "No files found in this folder structure\n";
            }
            
            echo '</pre>';
        } else {
            echo '<div class="alert alert-warning">⚠️ Upload folder does not exist: ' . htmlspecialchars($base_dir) . '</div>';
        }
        ?>

        <h3 class="mt-4">Permissions Check</h3>
        
        <?php
        $uploads_dir = __DIR__ . '/../uploads';
        $base_dir = __DIR__ . '/../uploads/' . $tenant_id . '/' . $branch_id . '/umrah';
        
        echo '<table class="table table-sm">';
        echo '<tr><th>Path</th><th>Exists</th><th>Writable</th><th>Permissions</th></tr>';
        
        // Check various directories
        $paths = [
            'uploads' => $uploads_dir,
            'tenant folder' => __DIR__ . '/../uploads/' . $tenant_id,
            'branch folder' => __DIR__ . '/../uploads/' . $tenant_id . '/' . $branch_id,
            'umrah folder' => $base_dir,
        ];
        
        foreach ($paths as $name => $path) {
            $exists = is_dir($path) ? '✅ Yes' : '❌ No';
            $writable = is_writable($path) ? '✅ Yes' : (is_dir($path) ? '❌ No' : 'N/A');
            $perms = is_dir($path) ? substr(sprintf('%o', fileperms($path)), -4) : 'N/A';
            
            echo '<tr>';
            echo '<td><code>' . htmlspecialchars($path) . '</code></td>';
            echo '<td>' . $exists . '</td>';
            echo '<td>' . $writable . '</td>';
            echo '<td>' . $perms . '</td>';
            echo '</tr>';
        }
        
        echo '</table>';
        ?>

        <h3 class="mt-4">Debugging Info</h3>
        
        <div class="alert alert-secondary">
            <strong>REQUEST_URI:</strong> <code><?= htmlspecialchars($_SERVER['REQUEST_URI']) ?></code><br>
            <strong>Script Path:</strong> <code><?= htmlspecialchars(__FILE__) ?></code><br>
            <strong>Base Directory:</strong> <code><?= htmlspecialchars(__DIR__) ?></code><br>
            
            <?php
            $request_uri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
            $base_path = str_replace('/scripts/verify-upload-paths.php', '', $request_uri);
            ?>
            
            <strong>Calculated Base Path:</strong> <code><?= htmlspecialchars($base_path) ?></code><br>
            <strong>Expected File URL Pattern:</strong> <code><?= htmlspecialchars($base_path) ?>/uploads/{tenant}/{branch}/umrah/{family}/{filename}</code>
        </div>

        <hr>
        
        <div class="alert alert-info">
            <h5>How to Fix 404 Issues:</h5>
            <ol>
                <li>Check "File System Check" section above</li>
                <li>Verify "Permissions Check" shows all folders writable</li>
                <li>Click "Test Link" to verify file is accessible</li>
                <li>If files missing: Re-upload them</li>
                <li>If 404 persists: Check file path in database matches filesystem</li>
            </ol>
        </div>

        <a href="../admin/umrah.php" class="btn btn-primary">Back to Umrah Management</a>
    </div>
</body>
</html>
