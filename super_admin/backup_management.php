<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

header("X-XSS-Protection: 1; mode=block");
header("X-Content-Type-Options: nosniff");
header("X-Frame-Options: DENY");
header("Referrer-Policy: strict-origin-when-cross-origin");

$sessionTimeout = 30 * 60;
if (isset($_SESSION['last_activity']) && (time() - $_SESSION['last_activity'] > $sessionTimeout)) {
    session_unset();
    session_destroy();
    header('Location: ../login.php?timeout=1');
    exit();
}
$_SESSION['last_activity'] = time();

if (!isset($_SESSION['user_id']) || !isset($_SESSION['role']) || $_SESSION['role'] !== 'super_admin' || !is_null($_SESSION['tenant_id'])) {
    header('Location: ../login.php');
    exit();
}
require_once '../includes/db.php';

function executeMysqldumpSafely($mysqldump, $host, $user, $pass, $database, $outputFile) {
    $cmd = [
        escapeshellarg($mysqldump),
        '--no-tablespaces',
        '--single-transaction',
        '--quick',
        '--lock-tables=false',
        '--host=' . escapeshellarg($host),
        '--user=' . escapeshellarg($user),
    ];
    if (!empty($pass)) {
        $cmd[] = '--password=' . escapeshellarg($pass);
    }
    $cmd[] = escapeshellarg($database);
    $descriptorspec = [
        0 => ['pipe', 'r'],
        1 => ['file', $outputFile, 'w'],
        2 => ['pipe', 'w']
    ];
    $process = proc_open(implode(' ', $cmd), $descriptorspec, $pipes);
    if (!is_resource($process)) {
        throw new Exception("Failed to execute mysqldump");
    }
    fclose($pipes[0]);
    $stderr = stream_get_contents($pipes[2]);
    fclose($pipes[2]);
    $return_value = proc_close($process);
    if ($return_value !== 0) {
        throw new Exception("Backup command failed");
    }
    if (!file_exists($outputFile) || filesize($outputFile) === 0) {
        throw new Exception("Backup file was not created or is empty");
    }
    return true;
}

$success_message = isset($_SESSION['success_message']) ? $_SESSION['success_message'] : null;
$error_message = isset($_SESSION['error_message']) ? $_SESSION['error_message'] : null;
unset($_SESSION['success_message']);
unset($_SESSION['error_message']);

$redirect_url = $_SERVER['PHP_SELF'];
if (!empty($_GET)) {
    $redirect_url .= '?' . http_build_query($_GET);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['backup_database'])) {
    try {
        $backup_dir = '../backups';
        if (!file_exists($backup_dir)) {
            mkdir($backup_dir, 0755, true);
        }
        $timestamp = date('Ymd_His');
        $filename = "backup_{$timestamp}.sql";
        $abs_path = $backup_dir . '/' . $filename;
        $host = DB_SERVER;
        $user = DB_USERNAME;
        $pass = DB_PASSWORD;
        $name = DB_NAME;
        if (empty($host) || empty($user) || empty($name)) {
            throw new Exception("Database host, username, and database name are required");
        }
        $dumpOk = false;
        $mysqldump_available = false;
        $mysqldump_paths = [
            '/usr/bin/mysqldump',
            '/usr/local/bin/mysqldump',
            '/opt/local/bin/mysqldump',
            'mysqldump'
        ];
        foreach ($mysqldump_paths as $mysqldump) {
            if (is_executable($mysqldump)) {
                $mysqldump_available = true;
                try {
                    if (executeMysqldumpSafely($mysqldump, $host, $user, $pass, $name, $abs_path)) {
                        $dumpOk = true;
                        break;
                    }
                } catch (Exception $e) {
                    continue;
                }
            }
        }
        if (!$dumpOk) {
            try {
                $dsn = "mysql:host={$host};dbname={$name};charset=utf8mb4";
                $db_options = [
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_TIMEOUT => 5,
                    PDO::MYSQL_ATTR_USE_BUFFERED_QUERY => true
                ];
                $pdo2 = new PDO($dsn, $user, $pass, $db_options);
                $fh = fopen($abs_path, 'w');
                if ($fh === false) {
                    throw new Exception("Failed to open file for writing: $abs_path");
                }
                $header = "-- PHP MySQL Backup\n" .
                          "-- Generated: " . date('Y-m-d H:i:s') . "\n" .
                          "-- Host: $host\n" .
                          "-- Database: $name\n" .
                          "SET NAMES utf8mb4;\n\n";
                fwrite($fh, $header);
                $tables = $pdo2->query("SHOW TABLES")->fetchAll(PDO::FETCH_COLUMN);
                $allowed_tables = [];
                $info_result = $pdo2->query("SELECT TABLE_NAME FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE()");
                while ($row = $info_result->fetch(PDO::FETCH_ASSOC)) {
                    $allowed_tables[] = $row['TABLE_NAME'];
                }
                foreach ($tables as $table) {
                    if (!in_array($table, $allowed_tables)) {
                        continue;
                    }
                    try {
                        $table_identifier = '`' . str_replace('`', '``', $table) . '`';
                        $create_result = $pdo2->query("SHOW CREATE TABLE {$table_identifier}");
                        if (!$create_result) {
                            continue;
                        }
                        $create_table = $create_result->fetch(PDO::FETCH_NUM)[1];
                        $create_table_sql = "DROP TABLE IF EXISTS {$table_identifier};\n" . $create_table . ";\n\n";
                        fwrite($fh, $create_table_sql);
                        $select_stmt = $pdo2->prepare("SELECT * FROM {$table_identifier}");
                        $select_stmt->execute();
                        while ($row = $select_stmt->fetch(PDO::FETCH_ASSOC)) {
                            $columns = array_map(function($k) { return "`" . str_replace('`', '``', $k) . "`"; }, array_keys($row));
                            $values = array_map(function($v) {
                                if ($v === null) return 'NULL';
                                return "'" . str_replace(["\\", "'"], ["\\\\", "\\'"], (string)$v) . "'";
                            }, array_values($row));
                            $insert_sql = sprintf(
                                "INSERT INTO %s (%s) VALUES (%s);\n",
                                $table_identifier,
                                implode(',', $columns),
                                implode(',', $values)
                            );
                            fwrite($fh, $insert_sql);
                        }
                        fwrite($fh, "\n");
                    } catch (PDOException $e) {
                        continue;
                    }
                }
                fclose($fh);
                $dumpOk = true;
            } catch (PDOException $e) {
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
        $_SESSION['error_message'] = "Error creating backup: " . $e->getMessage();
        header('Location: ' . $redirect_url);
        exit();
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_backup'])) {
    try {
        $backup_file = $_POST['backup_file'];
        $backup_dir = '../backups';
        $full_path = $backup_dir . '/' . basename($backup_file);
        if (!file_exists($full_path)) {
            throw new Exception("Backup file not found");
        }
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
    usort($backup_files, function($a, $b) {
        return $b['date'] - $a['date'];
    });
}

try {
    $stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
    $stmt->execute([$_SESSION['user_id']]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    $settingStmt = $pdo->query("SELECT * FROM settings WHERE id = 1");
    $settings = $settingStmt->fetch(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $user = null;
    $settings = ['agency_name' => 'Default Name'];
}

$profilePic = !empty($user['profile_pic']) ? htmlspecialchars($user['profile_pic']) : 'default-avatar.jpg';
$imagePath = "../assets/images/user/" . $profilePic;

function format_bytes($bytes, $precision = 2) {
    $units = ['B', 'KB', 'MB', 'GB', 'TB'];
    $bytes = max($bytes, 0);
    $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
    $pow = min($pow, count($units) - 1);
    $bytes /= (1 << (10 * $pow));
    return round($bytes, $precision) . ' ' . $units[$pow];
}
?>
<?php include '../includes/header_super_admin.php'; ?>
<style>
:root {
    --brand: #4099ff;
    --brand2: #2ed8b6;
    --bg: #f0f2f5;
    --surface: #fff;
    --border: #e5e7eb;
    --text: #1f2937;
    --muted: #6b7280;
    --radius: 12px;
    --grad: linear-gradient(135deg, var(--brand), var(--brand2));
}
* { box-sizing: border-box; margin: 0; padding: 0; }
body { font-family: system-ui, -apple-system, sans-serif; background: var(--bg); color: var(--text); font-size: 14px; }
.page-header.card {
    background: var(--grad) !important; color: #fff; border: none !important;
    margin-bottom: 20px; padding: 22px 28px !important;
    box-shadow: 0 4px 20px rgba(64,153,255,0.3); border-radius: 12px;
    position: relative; overflow: hidden;
}
.page-header.card::after {
    content: ''; position: absolute; inset: 0;
    background: radial-gradient(ellipse at 20% 50%, rgba(255,255,255,0.1) 0%, transparent 60%);
    pointer-events: none;
}
.page-header.card h5 { color: #fff !important; margin: 0; font-weight: 700; font-size: 1.15rem; position: relative; z-index: 1; }
.page-header.card .row { display: flex; align-items: center; justify-content: space-between; width: 100%; position: relative; z-index: 2; }
.page-header.card .col-md-6:last-child { text-align: right; margin-left: auto; }
.sa-table-wrap {
    background: var(--surface); border-radius: var(--radius);
    border: 1px solid var(--border); overflow-x: auto;
    box-shadow: 0 1px 3px rgba(0,0,0,0.04);
    -webkit-overflow-scrolling: touch;
}
.sa-toolbar {
    display: flex; align-items: center; justify-content: space-between;
    padding: 16px 20px; border-bottom: 1px solid var(--border); gap: 12px; flex-wrap: wrap;
}
.sa-toolbar h3 { font-size: 1rem; font-weight: 600; }
.sa-btn {
    display: inline-flex; align-items: center; gap: 6px;
    padding: 8px 16px; border: none; border-radius: 8px;
    font-size: .85rem; font-weight: 500; cursor: pointer;
    background: linear-gradient(135deg, var(--brand), var(--brand2));
    color: #fff; text-decoration: none; transition: opacity .15s;
}
.sa-btn:hover { opacity: .85; }
.sa-btn-outline {
    display: inline-flex; align-items: center; gap: 6px;
    padding: 8px 16px; border: 1px solid var(--border); border-radius: 8px;
    font-size: .85rem; font-weight: 500; cursor: pointer;
    background: var(--surface); color: var(--text); text-decoration: none;
}
.sa-btn-outline:hover { border-color: var(--brand); color: var(--brand); }
.sa-btn-icon {
    display: inline-flex; align-items: center; justify-content: center;
    width: 32px; height: 32px; border: none; border-radius: 6px;
    cursor: pointer; transition: all .15s;
    background: transparent; color: var(--muted); text-decoration: none;
}
.sa-btn-icon:hover { background: var(--bg); color: var(--text); }
.sa-btn-icon.danger:hover { background: #fee2e2; color: #ef4444; }
.sa-btn-icon.info:hover { background: #dbeafe; color: #2563eb; }
.sa-table { width: 100%; border-collapse: collapse; }
.sa-table th {
    text-align: left; padding: 12px 20px; font-size: .75rem;
    font-weight: 600; color: var(--muted); text-transform: uppercase;
    letter-spacing: .04em; background: var(--bg); border-bottom: 1px solid var(--border);
}
.sa-table td {
    padding: 12px 20px; font-size: .85rem;
    border-bottom: 1px solid var(--border); vertical-align: middle;
}
.sa-table tr:last-child td { border-bottom: none; }
.sa-table tr:hover td { background: #f8fafc; }
.sa-td-actions { white-space: nowrap; text-align: right; }
.file-cell { display: flex; align-items: center; gap: 10px; }
.file-icon {
    width: 36px; height: 36px; border-radius: 8px;
    display: flex; align-items: center; justify-content: center;
    background: #dbeafe; color: #2563eb; flex-shrink: 0;
}
.empty-state { text-align: center; padding: 48px 20px; color: var(--muted); }
.sa-alert {
    display: flex; align-items: center; gap: 8px;
    padding: 12px 16px; border-radius: 8px; margin-bottom: 16px; font-size: .85rem;
}
.sa-alert.success { background: #d1fae5; color: #065f46; }
.sa-alert.error { background: #fee2e2; color: #991b1b; }
.sa-modal-overlay {
    display: none; position: fixed; inset: 0; z-index: 9999;
    background: rgba(0,0,0,0.5); align-items: center; justify-content: center;
}
.sa-modal-overlay.active { display: flex; }
.sa-modal {
    background: var(--surface); border-radius: var(--radius);
    width: 90%; max-width: 500px; max-height: 90vh; overflow-y: auto;
    box-shadow: 0 20px 60px rgba(0,0,0,0.15);
}
.sa-modal-hdr {
    display: flex; align-items: center; justify-content: space-between;
    padding: 16px 20px; border-bottom: 1px solid var(--border);
}
.sa-modal-hdr h3 { font-size: 1rem; font-weight: 600; display: flex; align-items: center; gap: 8px; }
.sa-modal-close { background: none; border: none; font-size: 1.2rem; cursor: pointer; color: var(--muted); padding: 4px; line-height: 1; }
.sa-modal-body { padding: 20px; }
.sa-modal-ftr {
    display: flex; align-items: center; justify-content: flex-end;
    gap: 8px; padding: 16px 20px; border-top: 1px solid var(--border);
}
.sa-btn-secondary {
    display: inline-flex; align-items: center; gap: 6px;
    padding: 8px 16px; border: 1px solid var(--border); border-radius: 8px;
    font-size: .85rem; font-weight: 500; cursor: pointer;
    background: var(--surface); color: var(--text);
}
.sa-btn-secondary:hover { background: var(--bg); }
.sa-btn-danger {
    display: inline-flex; align-items: center; gap: 6px;
    padding: 8px 16px; border: none; border-radius: 8px;
    font-size: .85rem; font-weight: 500; cursor: pointer;
    background: #ef4444; color: #fff;
}
.sa-btn-danger:hover { opacity: .85; }
.sa-alert-warning {
    display: flex; align-items: center; gap: 8px;
    padding: 12px 16px; border-radius: 8px; background: #fef3c7; color: #92400e; font-size: .85rem; margin-top: 12px;
}
</style>

<!-- [ Main Content ] start -->
<div class="pcoded-main-container">
    <div class="pcoded-wrapper">
        <div class="pcoded-content">
            <div class="pcoded-inner-content">
                <!-- [ breadcrumb ] start -->
                <div class="page-header card">
                    <div class="row align-items-center">
                        <div class="col-md-6">
                            <h5 class="mb-0">
                                <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="vertical-align:middle;margin-right:8px">
                                    <ellipse cx="12" cy="5" rx="9" ry="3"/><path d="M21 12c0 1.66-4 3-9 3s-9-1.34-9-3"/><path d="M3 5v14c0 1.66 4 3 9 3s9-1.34 9-3V5"/>
                                </svg>
                                Database Backup Management
                            </h5>
                            <p class="mb-0 mt-1" style="font-size:14px;opacity:0.9">Create, download, and manage database backups</p>
                        </div>
                    </div>
                </div>
                <!-- [ breadcrumb ] end -->

                <div class="main-body">
                    <div class="page-wrapper">

                        <?php if (isset($success_message)): ?>
                        <div class="sa-alert success">
                            <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/></svg>
                            <?= htmlspecialchars($success_message) ?>
                        </div>
                        <?php endif; ?>

                        <?php if (isset($error_message)): ?>
                        <div class="sa-alert error">
                            <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><line x1="15" y1="9" x2="9" y2="15"/><line x1="9" y1="9" x2="15" y2="15"/></svg>
                            <?= htmlspecialchars($error_message) ?>
                        </div>
                        <?php endif; ?>

                        <div class="sa-table-wrap">
                            <div class="sa-toolbar">
                                <h3>
                                    <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="vertical-align:middle;margin-right:6px">
                                        <ellipse cx="12" cy="5" rx="9" ry="3"/><path d="M21 12c0 1.66-4 3-9 3s-9-1.34-9-3"/><path d="M3 5v14c0 1.66 4 3 9 3s9-1.34 9-3V5"/>
                                    </svg>
                                    Available Database Backups
                                </h3>
                                <button type="button" class="sa-btn" onclick="openModal('backupModal')">
                                    <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" y1="15" x2="12" y2="3"/></svg>
                                    Create Backup
                                </button>
                            </div>

                            <?php if (!empty($backup_files)): ?>
                            <table class="sa-table">
                                <thead>
                                    <tr>
                                        <th>Backup File</th>
                                        <th>Date Created</th>
                                        <th>Size</th>
                                        <th class="sa-td-actions">Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($backup_files as $backup): ?>
                                    <tr>
                                        <td>
                                            <div class="file-cell">
                                                <div class="file-icon">
                                                    <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/></svg>
                                                </div>
                                                <span><?= htmlspecialchars($backup['name']) ?></span>
                                            </div>
                                        </td>
                                        <td><?= date('Y-m-d H:i:s', $backup['date']) ?></td>
                                        <td><?= format_bytes($backup['size']) ?></td>
                                        <td class="sa-td-actions">
                                            <a href="download_backup.php?file=<?= urlencode($backup['name']) ?>"
                                               class="sa-btn-icon info" title="Download" download>
                                                <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" y1="15" x2="12" y2="3"/></svg>
                                            </a>
                                            <button type="button" class="sa-btn-icon danger" title="Delete"
                                                onclick="document.getElementById('deleteFileName').value='<?= htmlspecialchars($backup['name'], ENT_QUOTES) ?>';document.getElementById('deleteFileInfo').textContent='<?= htmlspecialchars($backup['name'], ENT_QUOTES) ?> | <?= date('Y-m-d H:i:s', $backup['date']) ?> | <?= format_bytes($backup['size']) ?>';openModal('deleteModal')">
                                                <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="3 6 5 6 21 6"/><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/></svg>
                                            </button>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                            <?php else: ?>
                            <div class="empty-state">
                                <svg xmlns="http://www.w3.org/2000/svg" width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" style="opacity:.4;margin-bottom:12px">
                                    <ellipse cx="12" cy="5" rx="9" ry="3"/><path d="M21 12c0 1.66-4 3-9 3s-9-1.34-9-3"/><path d="M3 5v14c0 1.66 4 3 9 3s9-1.34 9-3V5"/>
                                </svg>
                                <h5 style="margin-bottom:4px">No database backups found</h5>
                                <p style="color:var(--muted);font-size:.85rem">Create your first database backup by clicking the button above</p>
                            </div>
                            <?php endif; ?>
                        </div>

                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Create Backup Modal -->
    <div class="sa-modal-overlay" id="backupModal">
        <div class="sa-modal">
            <div class="sa-modal-hdr">
                <h3>
                    <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" y1="15" x2="12" y2="3"/></svg>
                    Create Database Backup
                </h3>
                <button type="button" class="sa-modal-close" onclick="closeModal('backupModal')">&times;</button>
            </div>
            <form method="POST">
                <div class="sa-modal-body">
                    <p style="margin-bottom:12px;color:var(--muted);font-size:.85rem">Create a complete backup of your database which can be downloaded or used for restoration.</p>
                    <div style="display:flex;align-items:center;gap:8px;padding:12px 16px;border-radius:8px;background:#dbeafe;color:#1e40af;font-size:.85rem">
                        <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><line x1="12" y1="16" x2="12" y2="12"/><line x1="12" y1="8" x2="12.01" y2="8"/></svg>
                        <span>Backup will use credentials from your database configuration.</span>
                    </div>
                </div>
                <div class="sa-modal-ftr">
                    <button type="button" class="sa-btn-secondary" onclick="closeModal('backupModal')">Cancel</button>
                    <button type="submit" name="backup_database" class="sa-btn">
                        <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" y1="15" x2="12" y2="3"/></svg>
                        Create Backup
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- Delete Backup Modal -->
    <div class="sa-modal-overlay" id="deleteModal">
        <div class="sa-modal">
            <div class="sa-modal-hdr">
                <h3>
                    <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="3 6 5 6 21 6"/><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/></svg>
                    Delete Backup
                </h3>
                <button type="button" class="sa-modal-close" onclick="closeModal('deleteModal')">&times;</button>
            </div>
            <form method="POST">
                <input type="hidden" name="backup_file" id="deleteFileName">
                <div class="sa-modal-body">
                    <p style="margin-bottom:12px">Are you sure you want to delete this backup?</p>
                    <p style="font-weight:600;font-size:.9rem" id="deleteFileInfo"></p>
                    <div class="sa-alert-warning">
                        <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"/><line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/></svg>
                        <span>This action cannot be undone.</span>
                    </div>
                </div>
                <div class="sa-modal-ftr">
                    <button type="button" class="sa-btn-secondary" onclick="closeModal('deleteModal')">Cancel</button>
                    <button type="submit" name="delete_backup" class="sa-btn-danger">
                        <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="3 6 5 6 21 6"/><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/></svg>
                        Delete Backup
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
<!-- Required Js -->
<script src="../assets/js/vendor-all.min.js"></script>
<script src="../assets/plugins/bootstrap/js/bootstrap.min.js"></script>
<script src="../assets/js/pcoded.min.js"></script>
<script>
function openModal(id) { document.getElementById(id).classList.add('active'); }
function closeModal(id) { document.getElementById(id).classList.remove('active'); }
document.querySelectorAll('.sa-modal-overlay').forEach(function(el) {
    el.addEventListener('click', function(e) {
        if (e.target === this) this.classList.remove('active');
    });
});
</script>
</body>
</html>
