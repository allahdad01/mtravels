<?php
/**
 * Database Migration UI (Super Admin) — one-click runner for
 * database_migration_umrah_full.sql
 *
 * Read-only on load: shows the migration file's statements, the tables it
 * will create, and what already exists in this database. Executes only when
 * the super admin POSTs with the CSRF token and types MIGRATE into the
 * confirm box.
 */

// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Set secure headers
header("X-XSS-Protection: 1; mode=block");
header("X-Content-Type-Options: nosniff");
header("X-Frame-Options: DENY");
header("Referrer-Policy: strict-origin-when-cross-origin");

// Check session timeout (30 minutes)
$sessionTimeout = 30 * 60;
if (isset($_SESSION['last_activity']) && (time() - $_SESSION['last_activity'] > $sessionTimeout)) {
    session_unset();
    session_destroy();
    header('Location: ../login.php?timeout=1');
    exit();
}
$_SESSION['last_activity'] = time();

// Super admin only
if (!isset($_SESSION['user_id']) || !isset($_SESSION['role']) || $_SESSION['role'] !== 'super_admin' || !is_null($_SESSION['tenant_id'])) {
    header('Location: ../login.php');
    exit();
}

// Create CSRF token if not exists
if (!isset($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// Database connection
require_once '../includes/db.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!isset($_POST['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
        die('Invalid CSRF token.');
    }
    if (!isset($_POST['migrate_confirm']) || strtoupper(trim($_POST['migrate_confirm'])) !== 'MIGRATE') {
        die('Confirmation text was not provided correctly.');
    }
}

$MIGRATION_FILE = dirname(__DIR__) . '/database_migration_umrah_full.sql';

/**
 * Split a SQL dump into individual statements.
 * Strips `--` line comments, honors `"`, `'` and backtick quoting so user
 * variables (SET @x := ... ; PREPARE stmt FROM @x ; EXECUTE stmt) survive.
 */
function dbm_split_statements($sql) {
    $statements = [];
    $buf = '';
    $quote = null;
    $len = strlen($sql);
    for ($i = 0; $i < $len; $i++) {
        $c = $sql[$i];
        $next = ($i + 1 < $len) ? $sql[$i + 1] : '';
        if ($quote !== null) {
            $buf .= $c;
            if ($c === '\\' && $next !== '') { $buf .= $next; $i++; }
            elseif ($c === $quote) { $quote = null; }
            continue;
        }
        if ($c === '`' || $c === "'" || $c === '"') { $quote = $c; $buf .= $c; continue; }
        if ($c === '-' && $next === '-') {
            while ($i < $len && $sql[$i] !== "\n") { $i++; }
            continue;
        }
        if ($c === ';') {
            $s = trim($buf);
            if ($s !== '') { $statements[] = $s; }
            $buf = '';
            continue;
        }
        $buf .= $c;
    }
    $s = trim($buf);
    if ($s !== '') { $statements[] = $s; }
    return $statements;
}

function dbm_one_line($stmt, $max = 110) {
    $s = preg_replace('/\s+/', ' ', trim($stmt));
    if (strlen($s) > $max) { $s = substr($s, 0, $max - 3) . '...'; }
    return $s;
}

$fileExists = is_file($MIGRATION_FILE);
$raw = $fileExists ? (string)file_get_contents($MIGRATION_FILE) : '';
if (strpos($raw, "\xEF\xBB\xBF") === 0) { $raw = substr($raw, 3); }
$statements = $fileExists ? dbm_split_statements($raw) : [];
$expectedTables = [];
$byType = ['create' => [], 'alter' => [], 'other' => []];
if ($fileExists) {
    preg_match_all('/CREATE TABLE IF NOT EXISTS `(umrah_[a-z_]+)`/', $raw, $m);
    $expectedTables = array_values(array_unique($m[1]));
    foreach ($statements as $st) {
        if (preg_match('/^CREATE TABLE IF NOT EXISTS `(umrah_[a-z_]+)`/i', trim($st), $mm)) {
            $byType['create'][$mm[1]] = true;
        } elseif (preg_match('/^(ALTER TABLE|UPDATE|INSERT INTO|SET |DELETE)/i', trim($st))) {
            $byType[mb_strpos(trim($st), 'ALTER TABLE') === 0 ? 'alter' : 'other'] = null;
        } else {
            $byType['other'] = null;
        }
    }
}

$umrahTables = [];
try {
    $umrahTables = array_values(array_filter($pdo->query('SHOW TABLES')->fetchAll(PDO::FETCH_COLUMN), fn($t) => strpos($t, 'umrah_') === 0));
} catch (PDOException $e) { }

$present = array_fill_keys($umrahTables, true);
$missingTables = array_values(array_filter($expectedTables, fn($t) => !isset($present[$t])));

$results = null;
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    set_time_limit(300);
    $results = [];
    $failures = 0;
    $dtStart = microtime(true);
    foreach ($statements as $stmt) {
        $label = dbm_one_line($stmt);
        try {
            $st = $pdo->query($stmt);
            if ($st instanceof PDOStatement) { $st->closeCursor(); }
            $results[] = ['ok' => true, 'sql' => $label, 'error' => ''];
        } catch (PDOException $e) {
            $results[] = ['ok' => false, 'sql' => $label, 'error' => $e->getMessage()];
            $failures++;
        }
    }
    $elapsed = round(microtime(true) - $dtStart, 2);
    try {
        $umrahTables = array_values(array_filter($pdo->query('SHOW TABLES')->fetchAll(PDO::FETCH_COLUMN), fn($t) => strpos($t, 'umrah_') === 0));
    } catch (PDOException $e) { $umrahTables = []; }
    $present = array_fill_keys($umrahTables, true);
    $missingTables = array_values(array_filter($expectedTables, fn($t) => !isset($present[$t])));
}
?>
<?php include '../includes/header_super_admin.php'; ?>

<!-- [ Main Content ] start -->
<div class="pcoded-main-container">
    <div class="pcoded-wrapper">
        <div class="pcoded-content">
            <div class="pcoded-inner-content">
                <div class="main-body">
                    <div class="page-wrapper">
                        <!-- [ Main Content ] start -->
                        <div class="main-content">
                            <div class="page-header card">
                                <div class="row align-items-center">
                                    <div class="col-md-6">
                                        <h5 class="mb-0">DB Migration</h5>
                                        <p class="page-desc">Apply <code>database_migration_umrah_full.sql</code> to this database.</p>
                                    </div>
                                    <div class="col-md-6 text-end">
                                        <a href="dashboard.php" class="btn btn-outline-secondary btn-sm">Back to Dashboard</a>
                                    </div>
                                </div>
                            </div>

                            <style>
                                :root {
                                    --primary: #4099ff;
                                    --surface: #ffffff;
                                    --surface2: #f3f8ff;
                                    --text: #1a2332;
                                    --muted: #6b7280;
                                    --border: #e2e8f0;
                                    --green: #10b981;
                                    --red: #ef4444;
                                    --amber: #f59e0b;
                                    --blue: #3b82f6;
                                }
                                .dbm-stat { font-size: 1.6rem; font-weight: 700; line-height: 1.1; }
                                .dbm-log { max-height: 420px; overflow: auto; }

                                /* ─── PILLS ─────────────────────────────── */
                                .dbm-pill {
                                    font-size: 0.65rem; font-weight: 700; padding: 3px 10px; border-radius: 20px;
                                    text-transform: uppercase; letter-spacing: 0.04em; white-space: nowrap;
                                    display: inline-flex; align-items: center; gap: 4px;
                                }
                                .dbm-pill-green { background: rgba(34,211,160,0.12); color: var(--green); }
                                .dbm-pill-red { background: rgba(244,63,94,0.12); color: var(--red); }
                                .dbm-pill-amber { background: rgba(245,158,11,0.12); color: var(--amber); }
                                .dbm-pill-blue { background: rgba(56,189,248,0.12); color: var(--blue); }
                                .dbm-pill-neutral { background: rgba(107,114,128,0.1); color: var(--muted); }

                                /* ─── TABLE TAGS ─────────────────────────── */
                                .dbm-tag {
                                    display: inline-flex; align-items: center; gap: 5px;
                                    font-size: 0.72rem; font-weight: 600; font-family: 'Courier New', monospace;
                                    padding: 3px 9px; border-radius: 6px; margin: 2px 4px 2px 0;
                                }
                                .dbm-tag-ok { background: rgba(34,211,160,0.1); color: var(--green); }
                                .dbm-tag-missing { background: rgba(244,63,94,0.1); color: var(--red); }
                                .dbm-tag-neutral { background: rgba(107,114,128,0.08); color: var(--muted); }

                                /* ─── ALERTS ────────────────────────────── */
                                .dbm-alert { padding: 12px 16px; border-radius: 8px; font-size: 0.88rem; margin-bottom: 14px; }
                                .dbm-alert-success { background: #d1fae5; color: #065f46; }
                                .dbm-alert-error { background: #fee2e2; color: #991b1b; }
                                .dbm-alert-warning { background: #fef3c7; color: #92400e; }
                            </style>

                            <div class="row mb-3">
                                <div class="col-md-4">
                                    <div class="card">
                                        <div class="card-body">
                                            <div class="text-muted small text-uppercase">Migration file</div>
                                            <div class="dbm-stat <?= $fileExists ? 'text-success' : 'text-danger' ?>">
                                                <?= $fileExists ? 'Found' : 'Missing' ?>
                                            </div>
                                            <div class="text-muted small"><?= htmlspecialchars(basename($MIGRATION_FILE)) ?></div>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-md-4">
                                    <div class="card">
                                        <div class="card-body">
                                            <div class="text-muted small text-uppercase">Statements</div>
                                            <div class="dbm-stat"><?= count($statements) ?></div>
                                            <div class="text-muted small"><?= count($byType['create']) ?> table creates</div>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-md-4">
                                    <div class="card">
                                        <div class="card-body">
                                            <div class="text-muted small text-uppercase">Umrah tables present</div>
                                            <div class="dbm-stat"><?= count($umrahTables) ?> <small class="text-muted" style="font-size:.75rem">of <?= count($expectedTables) ?> expected</small></div>
                                            <div class="text-muted small"><?= count($missingTables) ?> missing</div>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <div class="row">
                                <div class="col-md-7">
                                    <div class="card mb-3">
                                        <div class="card-header bg-white py-2"><strong>Expected tables</strong> <span class="text-muted small">(from migration file)</span></div>
                                        <div class="card-body py-2">
                                            <?php if (!$expectedTables): ?>
                                                <p class="text-muted mb-0">No table creates found in the migration file.</p>
                                            <?php else: ?>
                                                <?php foreach ($expectedTables as $t): ?>
                                                    <?php $ok = isset($present[$t]); ?>
                                                    <span class="dbm-tag <?= $ok ? 'dbm-tag-ok' : 'dbm-tag-missing' ?>">
                                                        <?= $ok ? '&#10003;' : '&#10007;' ?> <?= htmlspecialchars($t) ?>
                                                    </span>
                                                <?php endforeach; ?>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-md-5">
                                    <div class="card mb-3">
                                        <div class="card-header bg-white py-2"><strong>Other umrah tables in this DB</strong></div>
                                        <div class="card-body py-2">
                                            <?php $extra = array_values(array_filter($umrahTables, fn($t) => !in_array($t, $expectedTables, true))); ?>
                                            <?php if (!$extra): ?>
                                                <p class="text-muted mb-0">None.</p>
                                            <?php else: ?>
                                                <?php foreach ($extra as $t): ?>
                                                    <span class="dbm-pill dbm-pill-neutral"><?= htmlspecialchars($t) ?></span>
                                                <?php endforeach; ?>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <?php if ($results !== null): ?>
                            <div class="card mb-3">
                                <div class="card-header bg-white py-2">
                                    <strong>Run results</strong>
                                    <span class="dbm-pill <?= $failures === 0 ? 'dbm-pill-green' : 'dbm-pill-red' ?>">
                                        <?= $failures === 0 ? 'All ' . count($results) . ' statements OK' : $failures . ' of ' . count($results) . ' failed' ?>
                                    </span>
                                    <span class="text-muted small">(<?= $elapsed ?>s)</span>
                                </div>
                                <div class="card-body dbm-log p-0">
                                    <table class="table table-sm table-striped mb-0">
                                        <thead><tr><th style="width:60px">#</th><th>Result</th><th>Statement</th></tr></thead>
                                        <tbody>
                                            <?php foreach ($results as $i => $r): ?>
                                            <tr>
                                                <td class="text-muted"><?= $i + 1 ?></td>
                                                <td><?= $r['ok'] ? '<span class="dbm-pill dbm-pill-green">OK</span>' : '<span class="dbm-pill dbm-pill-red">FAIL</span>' ?></td>
                                                <td>
                                                    <code><?= htmlspecialchars($r['sql']) ?></code>
                                                    <?php if (!$r['ok']): ?>
                                                    <div class="text-danger mt-1"><code><?= htmlspecialchars($r['error']) ?></code></div>
                                                    <?php endif; ?>
                                                </td>
                                            </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                            <?php endif; ?>

                            <?php if ($missingTables || $results === null): ?>
                            <div class="card">
                                <div class="card-header bg-white py-2"><strong>Run migration</strong></div>
                                <div class="card-body">
                                    <?php if (!$fileExists): ?>
                                        <div class="dbm-alert dbm-alert-error mb-0">Migration file not found at <code><?= htmlspecialchars($MIGRATION_FILE) ?></code></div>
                                    <?php else: ?>
                                        <?php if ($missingTables): ?>
                                            <div class="dbm-alert dbm-alert-warning">
                                                <strong>Warning:</strong> The following expected tables are missing and will be created:
                                                <?php foreach ($missingTables as $t): ?><span class="dbm-pill dbm-pill-red"><?= htmlspecialchars($t) ?></span><?php endforeach; ?>
                                            </div>
                                        <?php endif; ?>
                                        <div class="dbm-alert dbm-alert-error">
                                            <strong>This modifies the live database.</strong> Run it once only — the statement block that inserts
                                            <code>umrah_service_statuses</code> rows is not guarded and will duplicate on a second run.
                                            Take a backup first.
                                        </div>
                                        <form method="post" onsubmit="return confirm('Run the migration against this database now?');">
                                            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">
                                            <div class="form-group">
                                                <label for="migrate_confirm" class="text-muted">Type <strong>MIGRATE</strong> to enable the button</label>
                                                <input type="text" class="form-control" id="migrate_confirm" name="migrate_confirm"
                                                       placeholder="MIGRATE" autocomplete="off" style="max-width:260px"
                                                       oninput="document.getElementById('migrateBtn').disabled = (this.value.toUpperCase() !== 'MIGRATE');">
                                            </div>
                                            <button type="submit" id="migrateBtn" class="btn btn-danger" disabled>Run migration</button>
                                            <button type="button" class="btn btn-outline-secondary" onclick="location.reload()">Refresh status</button>
                                        </form>
                                    <?php endif; ?>
                                </div>
                            </div>
                            <?php endif; ?>

                        </div>
                        <!-- [ Main Content ] end -->
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
<!-- [ Main Content ] end -->
 <!-- Required Js -->
<script src="../assets/js/vendor-all.min.js"></script>
<script src="../assets/plugins/bootstrap/js/bootstrap.min.js"></script>
<script src="../assets/js/pcoded.min.js"></script>
</body>
</html>