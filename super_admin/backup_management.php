<?php
// Include database security module for input validation
require_once 'includes/db_security.php';

// Include security module
require_once 'security.php';

// Enforce authentication
enforce_auth();

// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Check if user is logged in
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header('Location: ../login.php');
    exit();
}
require_once '../includes/conn.php';
require_once '../includes/db.php';

// Initialize messages
$success_message = isset($_SESSION['success_message']) ? $_SESSION['success_message'] : null;
$error_message = isset($_SESSION['error_message']) ? $_SESSION['error_message'] : null;

// Clear session messages after retrieving them
unset($_SESSION['success_message']);
unset($_SESSION['error_message']);

// Build redirect URL with current query parameters
$redirect_url = $_SERVER['PHP_SELF'];
if (!empty($_GET)) {
    $redirect_url .= '?' . http_build_query($_GET);
}

// Handle database backup
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['backup_database'])) {
    try {
        // Define backup directory
        $backup_dir = '../backups';
        
        // Create directory if it doesn't exist
        if (!file_exists($backup_dir)) {
            mkdir($backup_dir, 0755, true);
        }
        
        // Generate backup filename with timestamp
        $timestamp = date('Ymd_His');
        $filename = "backup_{$timestamp}.sql";
        $abs_path = $backup_dir . '/' . $filename;
        
        // Read database credentials from conn.php
        $conn_file_path = '../includes/conn.php';
        if (!file_exists($conn_file_path)) {
            throw new Exception("Database connection file not found");
        }
        
        // Read the contents of conn.php
        $conn_file_contents = file_get_contents($conn_file_path);
        
        // Extract database credentials using regex
        preg_match('/new\s+mysqli\s*\(\s*"([^"]*)",\s*"([^"]*)",\s*"([^"]*)",\s*"([^"]*)"\s*\)/', $conn_file_contents, $matches);
        
        if (count($matches) !== 5) {
            throw new Exception("Could not parse database credentials from conn.php");
        }
        
        // Assign extracted credentials
        $host = $matches[1];
        $user = $matches[2];
        $pass = $matches[3];
        $name = $matches[4];
        
        // Validate required fields
        if (empty($host) || empty($user) || empty($name)) {
            throw new Exception("Database host, username, and database name are required");
        }
        
        // Attempt to use mysqldump if available
        $dumpOk = false;
        $mysqldump_available = false;
        
        // Check if mysqldump is available (with fallback methods)
        $mysqldump_paths = [
            '/usr/bin/mysqldump',
            '/usr/local/bin/mysqldump',
            '/opt/local/bin/mysqldump',
            'mysqldump'
        ];
        
        foreach ($mysqldump_paths as $mysqldump) {
            // Use is_executable for more reliable check
            if (is_executable($mysqldump)) {
                $mysqldump_available = true;
                
                // Prepare mysqldump command
                $cmd = sprintf(
                    '%s --no-tablespaces -h%s -u%s %s %s > %s', 
                    escapeshellcmd($mysqldump), 
                    escapeshellarg($host), 
                    escapeshellarg($user), 
                    $pass ? '-p' . escapeshellarg($pass) : '', 
                    escapeshellarg($name), 
                    escapeshellarg($abs_path)
                );
                
                // Try to execute mysqldump
                $ret = null; 
                $output = [];
                
                // Use different methods to run the command
                if (function_exists('exec')) {
                    exec($cmd, $output, $ret);
                } elseif (function_exists('system')) {
                    $ret = system($cmd, $output);
                } else {
                    // Skip this method if no shell execution is available
                    continue;
                }
                
                $dumpOk = ($ret === 0) && file_exists($abs_path) && filesize($abs_path) > 0;
                
                // If successful, break the loop
                if ($dumpOk) {
                    break;
                }
            }
        }
        
        // Fallback to PDO-based dump if mysqldump fails or is unavailable
        if (!$dumpOk) {
            try {
                // Test the connection with the provided credentials
                $dsn = "mysql:host={$host};dbname={$name};charset=utf8mb4";
                $db_options = [
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_TIMEOUT => 5,
                    PDO::MYSQL_ATTR_USE_BUFFERED_QUERY => true
                ];
                
                $pdo = new PDO($dsn, $user, $pass, $db_options);
                
                // Open backup file for writing
                $fh = fopen($abs_path, 'w');
                
                if ($fh === false) {
                    throw new Exception("Failed to open file for writing: $abs_path");
                }
                
                // Add header information
                $header = "-- PHP MySQL Backup\n" .
                          "-- Generated: " . date('Y-m-d H:i:s') . "\n" .
                          "-- Host: $host\n" .
                          "-- Database: $name\n" .
                          "SET NAMES utf8mb4;\n\n";
                fwrite($fh, $header);
                
                // Get tables
                $tables = $pdo->query("SHOW TABLES")->fetchAll(PDO::FETCH_COLUMN);
                
                // Export each table
                foreach ($tables as $table) {
                    // Get table structure
                    $create_table = $pdo->query("SHOW CREATE TABLE `{$table}`")->fetch(PDO::FETCH_NUM)[1];
                    $create_table_sql = "DROP TABLE IF EXISTS `{$table}`;\n" . $create_table . ";\n\n";
                    fwrite($fh, $create_table_sql);
                    
                    // Get table data
                    $stmt = $pdo->query("SELECT * FROM `{$table}`");
                    
                    // Only proceed if there are rows
                    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                        $columns = array_map(function($k) { return "`" . str_replace('`', '``', $k) . "`"; }, array_keys($row));
                        $values = array_map(function($v) {
                            if ($v === null) return 'NULL';
                            return "'" . str_replace(["\\", "'"], ["\\\\", "\\'"], (string)$v) . "'";
                        }, array_values($row));
                        
                        $insert_sql = sprintf(
                            "INSERT INTO `%s` (%s) VALUES (%s);\n", 
                            $table, 
                            implode(',', $columns), 
                            implode(',', $values)
                        );
                        fwrite($fh, $insert_sql);
                    }
                    fwrite($fh, "\n");
                }
                
                // Close the file
                fclose($fh);
                
                $dumpOk = true;
            } catch (PDOException $e) {
                // Log detailed PDO connection error
                error_log("PDO Backup Error: " . $e->getMessage());
                throw new Exception("Database connection failed: " . $e->getMessage());
            }
        }
        
        if (!$dumpOk) {
            throw new Exception("Both mysqldump and PDO backup methods failed. Backup not possible.");
        }
        
        $_SESSION['success_message'] = "Database backup created successfully: " . $filename;
        
        header('Location: ' . $redirect_url);
        exit();
    } catch (Exception $e) {
        // Log the full error for debugging
        error_log("Backup Creation Error: " . $e->getMessage());
        
        $_SESSION['error_message'] = "Error creating backup: " . $e->getMessage();
        header('Location: ' . $redirect_url);
        exit();
    }
}

// Handle backup deletion
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_backup'])) {
    try {
        $backup_file = $_POST['backup_file'];
        
        // Validate that the backup file exists and is in the backup directory
        $backup_dir = '../backups';
        $full_path = $backup_dir . '/' . basename($backup_file);
        
        if (!file_exists($full_path)) {
            throw new Exception("Backup file not found");
        }
        
        // Delete the backup file
        if (unlink($full_path)) {
            $_SESSION['success_message'] = "Backup deleted successfully: " . basename($backup_file);
        } else {
            throw new Exception("Failed to delete backup file");
        }
        
        header('Location: ' . $redirect_url);
        exit();
    } catch (Exception $e) {
        $_SESSION['error_message'] = "Error deleting backup: " . $e->getMessage();
        header('Location: ' . $redirect_url);
        exit();
    }
}

// Get available backup files
$backup_files = [];
$backup_dir = '../backups';

if (file_exists($backup_dir)) {
    $files = scandir($backup_dir);
    
    foreach ($files as $file) {
        if (pathinfo($file, PATHINFO_EXTENSION) === 'sql') {
            $backup_files[] = [
                'name' => $file,
                'path' => $backup_dir . '/' . $file,
                'size' => filesize($backup_dir . '/' . $file),
                'date' => filemtime($backup_dir . '/' . $file)
            ];
        }
    }
    
    // Sort backup files by date (newest first)
    usort($backup_files, function($a, $b) {
        return $b['date'] - $a['date'];
    });
}

// Fetch user and settings data
try {
    $stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
    $stmt->execute([$_SESSION['user_id']]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    
    $settingStmt = $pdo->query("SELECT * FROM settings WHERE id = 1");
    $settings = $settingStmt->fetch(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log("Database Error: " . $e->getMessage());
    $user = null;
    $settings = ['agency_name' => 'Default Name'];
}

$profilePic = !empty($user['profile_pic']) ? htmlspecialchars($user['profile_pic']) : 'default-avatar.jpg';
$imagePath = "../assets/images/user/" . $profilePic;

// Helper function to format bytes
function format_bytes($bytes, $precision = 2) {
    $units = ['B', 'KB', 'MB', 'GB', 'TB'];
    $bytes = max($bytes, 0);
    $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
    $pow = min($pow, count($units) - 1);
    $bytes /= (1 << (10 * $pow));
    return round($bytes, $precision) . ' ' . $units[$pow];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <title><?= htmlspecialchars($settings['agency_name']) ?> - Database Backup Management</title>
    
    <!-- Meta -->
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, user-scalable=0, minimal-ui">
    <meta http-equiv="X-UA-Compatible" content="IE=edge" />
    
    <!-- Favicon icon -->
    <link rel="icon" href="../assets/images/log.png" type="image/x-icon">
    <!-- fontawesome icon -->
    <link rel="stylesheet" href="../assets/fonts/fontawesome/css/fontawesome-all.min.css">
    <!-- animation css -->
    <link rel="stylesheet" href="../assets/plugins/animation/css/animate.min.css">
    <!-- vendor css -->
    <link rel="stylesheet" href="../assets/css/style.css">
</head>
<body>
    <!-- [ Pre-loader ] start -->
    <div class="loader-bg">
        <div class="loader-track">
            <div class="loader-fill"></div>
        </div>
    </div>
    <!-- [ Pre-loader ] End -->
    
<?php include '../includes/header.php'; ?>

    <!-- [ Main Content ] start -->
    <div class="pcoded-main-container">
        <div class="pcoded-wrapper">
            <div class="pcoded-content">
                <div class="pcoded-inner-content">
                    <div class="main-body">
                        <div class="page-wrapper">
                            <div class="container mt-4">
                                <div class="d-flex justify-content-between align-items-center mb-4">
                                    <h2><i class="feather icon-database mr-2"></i><?= __('database_backup_management') ?></h2>
                                    <div>
                                        
                                        <button type="button" class="btn btn-outline-primary" data-toggle="modal" data-target="#backupModal">
                                            <i class="feather icon-download"></i> <?= __('create_backup') ?> (PHP)
                                        </button>
                                    </div>
                                </div>
                                
                                <?php if (isset($success_message)): ?>
                                    <div class="alert alert-success"><?php echo htmlspecialchars($success_message); ?></div>
                                <?php endif; ?>
                                
                                <?php if (isset($error_message)): ?>
                                    <div class="alert alert-danger"><?php echo htmlspecialchars($error_message); ?></div>
                                <?php endif; ?>
                                
                                <!-- Backup Management Card -->
                                <div class="card shadow-sm">
                                    <div class="card-header bg-primary text-white">
                                        <h5 class="mb-0"><i class="feather icon-list mr-2"></i><?= __('available_database_backups') ?></h5>
                                    </div>
                                    <div class="card-body">
                                        <?php if (empty($backup_files)): ?>
                                            <div class="text-center py-5">
                                                <i class="feather icon-database text-muted" style="font-size: 48px;"></i>
                                                <h5 class="mt-3"><?= __('no_database_backups_found') ?></h5>
                                                <p class="text-muted"><?= __('create_your_first_database_backup_by_clicking_the_button_above') ?></p>
                                            </div>
                                        <?php else: ?>
                                            <div class="table-responsive">
                                                <table class="table table-hover table-striped">
                                                    <thead class="thead-light">
                                                        <tr>
                                                            <th><?= __('backup_file') ?></th>
                                                            <th><?= __('date_created') ?></th>
                                                            <th><?= __('size') ?></th>
                                                            <th class="text-center"><?= __('actions') ?></th>
                                                        </tr>
                                                    </thead>
                                                    <tbody>
                                                        <?php foreach ($backup_files as $backup): ?>
                                                            <tr>
                                                                <td>
                                                                    <div class="d-flex align-items-center">
                                                                        <div class="avatar avatar-sm bg-light-primary rounded-circle text-primary mr-2">
                                                                            <i class="feather icon-file-text"></i>
                                                                        </div>
                                                                        <div>
                                                                            <?php echo htmlspecialchars($backup['name']); ?>
                                                                        </div>
                                                                    </div>
                                                                </td>
                                                                <td><?php echo date('Y-m-d H:i:s', $backup['date']); ?></td>
                                                                <td><?php echo format_bytes($backup['size']); ?></td>
                                                                <td class="text-center">
                                                                   
                                                                    <a href="<?php echo htmlspecialchars($backup['path']); ?>" 
                                                                       class="btn btn-icon btn-info btn-sm" 
                                                                       download
                                                                       title="<?= __('download_backup') ?>">
                                                                        <i class="feather icon-download"></i>
                                                                    </a>
                                                                    <button type="button" class="btn btn-icon btn-danger btn-sm" 
                                                                            data-toggle="modal" 
                                                                            data-target="#deleteModal_<?php echo md5($backup['name']); ?>" 
                                                                            title="<?= __('delete_backup') ?>">
                                                                        <i class="feather icon-trash-2"></i>
                                                                    </button>
                                                                </td>
                                                            </tr>
                                                        <?php endforeach; ?>
                                                    </tbody>
                                                </table>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                </div>
                                
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Create Backup Modal -->
    <div class="modal fade" id="backupModal" tabindex="-1" role="dialog" aria-hidden="true">
        <div class="modal-dialog" role="document">
            <div class="modal-content">
                <div class="modal-header bg-primary text-white">
                    <h5 class="modal-title"><i class="feather icon-download-cloud mr-2"></i><?= __('create_database_backup') ?></h5>
                    <button type="button" class="close text-white" data-dismiss="modal" aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                <form method="POST">
                    <div class="modal-body">
                        <p><?= __('create_a_complete_backup_of_your_database_which_can_be_downloaded_or_used_for_restoration') ?></p>
                        
                        <div class="alert alert-info">
                            <i class="feather icon-info mr-2"></i>
                            <?= __('backup_will_use_credentials_from_conn_php') ?>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-dismiss="modal"><?= __('cancel') ?></button>
                        <button type="submit" name="backup_database" class="btn btn-primary">
                            <i class="feather icon-download-cloud mr-1"></i><?= __('create_backup') ?>
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    
    <!-- Delete Modals for each backup -->
    <?php foreach ($backup_files as $backup): ?>
        <!-- Delete Modal -->
        <div class="modal fade" id="deleteModal_<?php echo md5($backup['name']); ?>" tabindex="-1" role="dialog" aria-hidden="true">
            <div class="modal-dialog" role="document">
                <div class="modal-content">
                    <div class="modal-header bg-danger text-white">
                        <h5 class="modal-title"><i class="feather icon-trash-2 mr-2"></i><?= __('delete_backup') ?></h5>
                        <button type="button" class="close text-white" data-dismiss="modal" aria-label="Close">
                            <span aria-hidden="true">&times;</span>
                        </button>
                    </div>
                    <form method="POST">
                        <div class="modal-body">
                            <input type="hidden" name="backup_file" value="<?php echo htmlspecialchars($backup['name']); ?>">
                            <p><?= __('are_you_sure_you_want_to_delete_this_backup') ?></p>
                            <p><strong><?= __('backup_file') ?>:</strong> <?php echo htmlspecialchars($backup['name']); ?></p>
                            <p><strong><?= __('created') ?>:</strong> <?php echo date('Y-m-d H:i:s', $backup['date']); ?></p>
                            <p><strong><?= __('size') ?>:</strong> <?php echo format_bytes($backup['size']); ?></p>
                            
                            <div class="alert alert-warning">
                                <i class="feather icon-alert-triangle mr-2"></i>
                                <?= __('this_action_cannot_be_undone') ?>
                            </div>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-secondary" data-dismiss="modal"><?= __('cancel') ?></button>
                            <button type="submit" name="delete_backup" class="btn btn-danger">
                                <i class="feather icon-trash-2 mr-1"></i><?= __('delete_backup') ?>
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    <?php endforeach; ?>
    
    <!-- Profile Modal -->
    <div class="modal fade" id="profileModal" tabindex="-1" role="dialog" aria-labelledby="profileModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header bg-primary text-white">
                    <h5 class="modal-title" id="profileModalLabel">
                        <i class="feather icon-user mr-2"></i><?= __('user_profile') ?>
                    </h5>
                    <button type="button" class="close text-white" data-dismiss="modal" aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                <div class="modal-body">
                    <div class="text-center mb-4">
                        <div class="position-relative d-inline-block">
                            <img src="<?= $imagePath ?>" 
                                class="rounded-circle profile-image" 
                                alt="User Profile Image" style="width: 120px; height: 120px; object-fit: cover;">
                            <div class="profile-status online"></div>
                        </div>
                        <h5 class="mt-3 mb-1"><?= !empty($user['name']) ? htmlspecialchars($user['name']) : 'Guest' ?></h5>
                        <p class="text-muted mb-0"><?= !empty($user['role']) ? htmlspecialchars($user['role']) : 'User' ?></p>
                    </div>

                    <div class="profile-info">
                        <div class="row">
                            <div class="col-sm-6 mb-3">
                                <div class="info-item">
                                    <label class="text-muted mb-1"><?= __('email') ?></label>
                                    <p class="mb-0"><?= !empty($user['email']) ? htmlspecialchars($user['email']) : 'Not Set' ?></p>
                                </div>
                            </div>
                            <div class="col-sm-6 mb-3">
                                <div class="info-item">
                                    <label class="text-muted mb-1"><?= __('phone') ?></label>
                                    <p class="mb-0"><?= !empty($user['phone']) ? htmlspecialchars($user['phone']) : 'Not Set' ?></p>
                                </div>
                            </div>
                            <div class="col-sm-6 mb-3">
                                <div class="info-item">
                                    <label class="text-muted mb-1"><?= __('join_date') ?></label>
                                    <p class="mb-0"><?= !empty($user['hire_date']) ? date('M d, Y', strtotime($user['hire_date'])) : 'Not Set' ?></p>
                                </div>
                            </div>
                            <div class="col-sm-6 mb-3">
                                <div class="info-item">
                                    <label class="text-muted mb-1"><?= __('address') ?></label>
                                    <p class="mb-0"><?= !empty($user['address']) ? htmlspecialchars($user['address']) : 'Not Set' ?></p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer bg-light">
                        <button type="button" class="btn btn-outline-secondary" data-dismiss="modal"><?= __('close') ?></button>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Required Js -->
    <script src="../assets/js/vendor-all.min.js"></script>
    <script src="../assets/plugins/bootstrap/js/bootstrap.min.js"></script>
    <script src="../assets/js/pcoded.min.js"></script>
    
    <script>
        // JavaScript for toggling full screen
        function toggleFullScreen() {
            var a = $(window).height() - 10;
            if (!document.fullscreenElement && 
                !document.mozFullScreenElement && 
                !document.webkitFullscreenElement) {
                if (document.documentElement.requestFullscreen) {
                    document.documentElement.requestFullscreen();
                } else if (document.documentElement.mozRequestFullScreen) {
                    document.documentElement.mozRequestFullScreen();
                } else if (document.documentElement.webkitRequestFullscreen) {
                    document.documentElement.webkitRequestFullscreen(Element.ALLOW_KEYBOARD_INPUT);
                }
            } else {
                if (document.cancelFullScreen) {
                    document.cancelFullScreen();
                } else if (document.mozCancelFullScreen) {
                    document.mozCancelFullScreen();
                } else if (document.webkitCancelFullScreen) {
                    document.webkitCancelFullScreen();
                }
            }
        }
    </script>
</body>
</html> 