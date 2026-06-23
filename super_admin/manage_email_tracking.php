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

if (!isset($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

require_once '../includes/db.php';

if (!function_exists('h')) {
    function h(string $s): string {
        return htmlspecialchars($s, ENT_QUOTES, 'UTF-8');
    }
}

// Pagination & filters
$page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
$perPage = 20;
$offset = ($page - 1) * $perPage;
$emailTypeFilter = $_GET['type'] ?? '';
$statusFilter = $_GET['status'] ?? ''; // 'opened' or 'not_opened'
$search = trim($_GET['search'] ?? '');

// Count
$countSql = "SELECT COUNT(*) FROM email_tracking WHERE 1=1";
$countParams = [];
if (!empty($emailTypeFilter)) {
    $countSql .= " AND email_type = ?";
    $countParams[] = $emailTypeFilter;
}
if ($statusFilter === 'opened') {
    $countSql .= " AND opened = 1";
} elseif ($statusFilter === 'not_opened') {
    $countSql .= " AND opened = 0";
}
if (!empty($search)) {
    $countSql .= " AND (recipient_email LIKE ? OR subject LIKE ? OR email_type LIKE ?)";
    $s = "%$search%";
    $countParams[] = $s; $countParams[] = $s; $countParams[] = $s;
}
$stmt = $pdo->prepare($countSql);
$stmt->execute($countParams);
$totalRecords = $stmt->fetchColumn();
$totalPages = ceil($totalRecords / $perPage);

// Main query
$sql = "SELECT * FROM email_tracking WHERE 1=1";
$queryParams = [];
if (!empty($emailTypeFilter)) {
    $sql .= " AND email_type = ?";
    $queryParams[] = $emailTypeFilter;
}
if ($statusFilter === 'opened') {
    $sql .= " AND opened = 1";
} elseif ($statusFilter === 'not_opened') {
    $sql .= " AND opened = 0";
}
if (!empty($search)) {
    $sql .= " AND (recipient_email LIKE ? OR subject LIKE ? OR email_type LIKE ?)";
    $s = "%$search%";
    $queryParams[] = $s; $queryParams[] = $s; $queryParams[] = $s;
}
$sql .= " ORDER BY sent_at DESC LIMIT ? OFFSET ?";
$queryParams[] = $perPage;
$queryParams[] = $offset;

$stmt = $pdo->prepare($sql);
$stmt->execute($queryParams);
$emails = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Stats
$stats = [];
try {
    $stmt = $pdo->query("SELECT COUNT(*) as total, SUM(opened) as opened_count FROM email_tracking");
    $stats = $stmt->fetch(PDO::FETCH_ASSOC);
    $stats['opened_percent'] = $stats['total'] > 0 ? round($stats['opened_count'] / $stats['total'] * 100) : 0;

    // Per-type stats
    $stmt = $pdo->query("SELECT email_type, COUNT(*) as cnt, SUM(opened) as opened FROM email_tracking GROUP BY email_type ORDER BY cnt DESC");
    $typeStats = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $typeStats = [];
}

// Unique email types for filter dropdown
$stmt = $pdo->query("SELECT DISTINCT email_type FROM email_tracking ORDER BY email_type");
$emailTypes = $stmt->fetchAll(PDO::FETCH_COLUMN);

include '../includes/header_super_admin.php';
?>

<style>
:root {
    --et-primary: #4099ff;
    --et-success: #2ed8b6;
    --et-danger: #ff5370;
    --et-warning: #ffb64d;
    --et-bg: #f4f6f9;
    --et-surface: #fff;
    --et-text: #2c3e50;
    --et-muted: #6c757d;
    --et-border: #e8ecf1;
    --et-radius: 12px;
    --et-shadow: 0 2px 8px rgba(0,0,0,.08);
}
.et-page { padding: 20px; }
.et-header { display:flex; justify-content:space-between; align-items:center; margin-bottom:24px; flex-wrap:wrap; gap:12px; }
.et-header h2 { margin:0; font-size:1.5rem; font-weight:600; color:var(--et-text); display:flex; align-items:center; gap:10px; }

.et-card { background:var(--et-surface); border-radius:var(--et-radius); box-shadow:var(--et-shadow); overflow:hidden; margin-bottom:20px; border:1px solid var(--et-border); }
.et-card-body { padding:20px; }

.et-stats { display:grid; grid-template-columns:repeat(auto-fit,minmax(160px,1fr)); gap:14px; margin-bottom:20px; }
.et-stat { background:var(--et-surface); border:1px solid var(--et-border); border-radius:10px; padding:16px; text-align:center; }
.et-stat-value { font-size:1.8rem; font-weight:700; color:var(--et-text); line-height:1.2; }
.et-stat-label { font-size:.78rem; color:var(--et-muted); text-transform:uppercase; letter-spacing:.5px; font-weight:600; margin-top:4px; }
.et-stat-icon { font-size:1.5rem; margin-bottom:6px; }

.et-filters { display:flex; gap:10px; margin-bottom:16px; flex-wrap:wrap; align-items:center; }
.et-filter-input { padding:7px 12px; border:1.5px solid var(--et-border); border-radius:6px; font-size:.85rem; }
.et-filter-input:focus { outline:none; border-color:var(--et-primary); }
.et-filter-select { padding:7px 12px; border:1.5px solid var(--et-border); border-radius:6px; font-size:.85rem; background:var(--et-surface); cursor:pointer; }
.et-filter-btn { padding:7px 16px; border:none; border-radius:6px; font-size:.85rem; font-weight:500; cursor:pointer; background:var(--et-primary); color:#fff; }
.et-filter-btn:hover { opacity:.85; }
.et-filter-clear { padding:7px 16px; border:1.5px solid var(--et-border); border-radius:6px; font-size:.85rem; background:var(--et-surface); color:var(--et-text); cursor:pointer; text-decoration:none; display:inline-flex; align-items:center; }
.et-filter-clear:hover { border-color:var(--et-primary); color:var(--et-primary); }

.et-table-wrap { overflow-x:auto; }
.et-table { width:100%; border-collapse:collapse; }
.et-table th { background:#f8f9fa; padding:10px 14px; text-align:left; font-size:.75rem; font-weight:600; color:var(--et-muted); text-transform:uppercase; letter-spacing:.5px; border-bottom:2px solid var(--et-border); white-space:nowrap; }
.et-table td { padding:10px 14px; font-size:.85rem; color:var(--et-text); border-bottom:1px solid var(--et-border); vertical-align:middle; }
.et-table tr:hover td { background:#f8f9fa; }

.et-badge { display:inline-flex; align-items:center; gap:4px; padding:3px 10px; border-radius:99px; font-size:.7rem; font-weight:600; white-space:nowrap; }
.et-badge-opened { background:#d4edda; color:#155724; }
.et-badge-not-opened { background:#f8d7da; color:#721c24; }
.et-badge-type { background:#e8f0fe; color:#1967d2; font-size:.7rem; }

.et-subject { max-width:320px; overflow:hidden; text-overflow:ellipsis; white-space:nowrap; }
.et-time { font-size:.78rem; color:var(--et-muted); font-family:monospace; }
.et-empty { text-align:center; padding:60px 20px; color:var(--et-muted); }
.et-empty i { font-size:2.5rem; margin-bottom:12px; opacity:.4; display:block; }

.et-pagination { display:flex; gap:4px; justify-content:center; margin-top:16px; flex-wrap:wrap; }
.et-pagination a, .et-pagination span { display:grid; place-items:center; width:34px; height:34px; border-radius:6px; font-size:.82rem; font-weight:600; text-decoration:none; border:1.5px solid var(--et-border); background:var(--et-surface); color:var(--et-text); }
.et-pagination a:hover { border-color:var(--et-primary); color:var(--et-primary); }
.et-pagination .active a { background:var(--et-primary); color:#fff; border-color:var(--et-primary); }
.et-pagination .disabled span { color:var(--et-muted); cursor:not-allowed; opacity:.5; }

.et-detail-modal .modal-dialog { max-width:650px; }
.et-detail-row { display:flex; gap:8px; padding:8px 0; border-bottom:1px solid var(--et-border); }
.et-detail-label { font-size:.78rem; font-weight:600; color:var(--et-muted); min-width:100px; text-transform:uppercase; letter-spacing:.3px; }
.et-detail-value { font-size:.85rem; color:var(--et-text); word-break:break-all; }

@media (max-width:768px) { .et-stats { grid-template-columns:1fr 1fr; } .et-subject { max-width:140px; } }
</style>

<div class="pcoded-main-container">
    <div class="pcoded-wrapper">
        <div class="pcoded-content">
            <div class="pcoded-inner-content">
                <div class="main-body">
                    <div class="page-wrapper">
                        <div class="et-page">

                            <div class="et-header">
                                <h2><i class="feather icon-mail" style="color:var(--et-primary)"></i> Email Tracking</h2>
                                <div style="display:flex;gap:8px;">
                                    <a href="platform_settings.php" class="et-filter-clear"><i class="feather icon-settings"></i> SMTP Settings</a>
                                    <a href="send_test_email.php" class="et-filter-clear"><i class="feather icon-send"></i> Test Email</a>
                                </div>
                            </div>

                            <!-- Stats -->
                            <div class="et-stats">
                                <div class="et-stat">
                                    <div class="et-stat-icon" style="color:var(--et-primary);"><i class="feather icon-send"></i></div>
                                    <div class="et-stat-value"><?= number_format($stats['total'] ?? 0) ?></div>
                                    <div class="et-stat-label">Total Sent</div>
                                </div>
                                <div class="et-stat">
                                    <div class="et-stat-icon" style="color:var(--et-success);"><i class="feather icon-eye"></i></div>
                                    <div class="et-stat-value"><?= number_format($stats['opened_count'] ?? 0) ?></div>
                                    <div class="et-stat-label">Opened</div>
                                </div>
                                <div class="et-stat">
                                    <div class="et-stat-icon" style="color:var(--et-danger);"><i class="feather icon-eye-off"></i></div>
                                    <div class="et-stat-value"><?= number_format(($stats['total'] ?? 0) - ($stats['opened_count'] ?? 0)) ?></div>
                                    <div class="et-stat-label">Not Opened</div>
                                </div>
                                <div class="et-stat">
                                    <div class="et-stat-icon" style="color:var(--et-warning);"><i class="feather icon-percent"></i></div>
                                    <div class="et-stat-value"><?= $stats['opened_percent'] ?? 0 ?>%</div>
                                    <div class="et-stat-label">Open Rate</div>
                                </div>
                            </div>

                            <!-- Type breakdown -->
                            <?php if (!empty($typeStats)): ?>
                            <div class="et-card">
                                <div class="et-card-body">
                                    <div style="display:flex;flex-wrap:wrap;gap:10px;">
                                        <?php foreach ($typeStats as $ts): ?>
                                        <a href="?type=<?= urlencode($ts['email_type']) ?>" style="display:flex;align-items:center;gap:8px;padding:8px 14px;border-radius:8px;border:1.5px solid var(--et-border);background:var(--et-surface);text-decoration:none;transition:all.15s;">
                                            <span class="et-badge et-badge-type"><?= h($ts['email_type']) ?></span>
                                            <span style="font-size:.82rem;font-weight:600;color:var(--et-text);"><?= $ts['cnt'] ?></span>
                                            <span style="font-size:.72rem;color:var(--et-muted);">(<?= $ts['opened'] ?> opened)</span>
                                        </a>
                                        <?php endforeach; ?>
                                    </div>
                                </div>
                            </div>
                            <?php endif; ?>

                            <!-- Filters -->
                            <div class="et-card">
                                <div class="et-card-body">
                                    <form method="GET" class="et-filters">
                                        <input type="text" name="search" class="et-filter-input" placeholder="Search email, subject, type..." value="<?= h($search) ?>" style="min-width:200px;">
                                        <select name="type" class="et-filter-select">
                                            <option value="">All Types</option>
                                            <?php foreach ($emailTypes as $t): ?>
                                            <option value="<?= h($t) ?>" <?= $emailTypeFilter === $t ? 'selected' : '' ?>><?= h($t) ?></option>
                                            <?php endforeach; ?>
                                        </select>
                                        <select name="status" class="et-filter-select">
                                            <option value="">All Status</option>
                                            <option value="opened" <?= $statusFilter === 'opened' ? 'selected' : '' ?>>Opened</option>
                                            <option value="not_opened" <?= $statusFilter === 'not_opened' ? 'selected' : '' ?>>Not Opened</option>
                                        </select>
                                        <button type="submit" class="et-filter-btn"><i class="feather icon-filter"></i> Filter</button>
                                        <?php if (!empty($search) || !empty($emailTypeFilter) || !empty($statusFilter)): ?>
                                        <a href="manage_email_tracking.php" class="et-filter-clear"><i class="feather icon-x"></i> Clear</a>
                                        <?php endif; ?>
                                    </form>

                                    <!-- Table -->
                                    <div class="et-table-wrap">
                                        <?php if (empty($emails)): ?>
                                        <div class="et-empty">
                                            <i class="feather icon-inbox"></i>
                                            <p>No emails found</p>
                                        </div>
                                        <?php else: ?>
                                        <table class="et-table">
                                            <thead>
                                                <tr>
                                                    <th>#</th>
                                                    <th>Recipient</th>
                                                    <th>Subject</th>
                                                    <th>Type</th>
                                                    <th>Source</th>
                                                    <th>Sent</th>
                                                    <th>Status</th>
                                                    <th>Opened</th>
                                                    <th style="text-align:right;">Actions</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <?php foreach ($emails as $i => $e): ?>
                                                <tr>
                                                <td style="color:var(--et-muted);font-size:.78rem;"><?= $offset + $i + 1 ?></td>
                                                <td><?= h($e['recipient_email']) ?></td>
                                                <td><div class="et-subject" title="<?= h($e['subject'] ?? '') ?>"><?= h($e['subject'] ?? '<em>—</em>') ?></div></td>
                                                <td><span class="et-badge et-badge-type"><?= h($e['email_type']) ?></span></td>
                                                <td><?= empty($e['tenant_id']) ? '<span class="et-badge" style="background:#4099ff;color:#fff;">Platform</span>' : '<span class="et-badge" style="background:#e8ecf1;color:#666;">Tenant #' . (int)$e['tenant_id'] . '</span>' ?></td>
                                                <td class="et-time" title="<?= h($e['sent_at']) ?>"><?= date('M j, H:i', strtotime($e['sent_at'])) ?></td>
                                                    <td>
                                                        <?php if ($e['opened']): ?>
                                                        <span class="et-badge et-badge-opened"><i class="feather icon-eye"></i> Opened</span>
                                                        <?php else: ?>
                                                        <span class="et-badge et-badge-not-opened"><i class="feather icon-eye-off"></i> Not Opened</span>
                                                        <?php endif; ?>
                                                    </td>
                                                    <td class="et-time">
                                                        <?php if ($e['opened'] && !empty($e['opened_at'])): ?>
                                                        <?= date('M j, H:i', strtotime($e['opened_at'])) ?>
                                                        <?php else: ?>
                                                        —
                                                        <?php endif; ?>
                                                    </td>
                                                    <td style="text-align:right;">
                                                        <button class="et-filter-clear" style="padding:4px 10px;font-size:.78rem;" onclick="viewDetails(<?= htmlspecialchars(json_encode($e)) ?>)">
                                                            <i class="feather icon-eye"></i>
                                                        </button>
                                                    </td>
                                                </tr>
                                                <?php endforeach; ?>
                                            </tbody>
                                        </table>

                                        <!-- Pagination -->
                                        <?php if ($totalPages > 1): ?>
                                        <div class="et-pagination">
                                            <li class="<?= $page <= 1 ? 'disabled' : '' ?>">
                                                <a href="?page=<?= $page - 1 ?><?= !empty($emailTypeFilter) ? '&type=' . urlencode($emailTypeFilter) : '' ?><?= !empty($statusFilter) ? '&status=' . urlencode($statusFilter) : '' ?><?= !empty($search) ? '&search=' . urlencode($search) : '' ?>">&laquo;</a>
                                            </li>
                                            <?php
                                            $start = max(1, $page - 2);
                                            $end = min($totalPages, $page + 2);
                                            for ($p = $start; $p <= $end; $p++):
                                            ?>
                                            <li class="<?= $p == $page ? 'active' : '' ?>">
                                                <a href="?page=<?= $p ?><?= !empty($emailTypeFilter) ? '&type=' . urlencode($emailTypeFilter) : '' ?><?= !empty($statusFilter) ? '&status=' . urlencode($statusFilter) : '' ?><?= !empty($search) ? '&search=' . urlencode($search) : '' ?>"><?= $p ?></a>
                                            </li>
                                            <?php endfor; ?>
                                            <li class="<?= $page >= $totalPages ? 'disabled' : '' ?>">
                                                <a href="?page=<?= $page + 1 ?><?= !empty($emailTypeFilter) ? '&type=' . urlencode($emailTypeFilter) : '' ?><?= !empty($statusFilter) ? '&status=' . urlencode($statusFilter) : '' ?><?= !empty($search) ? '&search=' . urlencode($search) : '' ?>">&raquo;</a>
                                            </li>
                                        </div>
                                        <?php endif; ?>
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
</div>

<!-- Details Modal -->
<div class="modal fade et-detail-modal" id="emailDetailModal" tabindex="-1" role="dialog">
    <div class="modal-dialog modal-dialog-centered" role="document">
        <div class="modal-content" style="border:none;border-radius:12px;box-shadow:0 10px 40px rgba(0,0,0,.15);">
            <div class="modal-header" style="border-bottom:1px solid var(--et-border);padding:16px 24px;">
                <h5 class="modal-title" style="font-size:1rem;font-weight:600;">Email Details</h5>
                <button type="button" class="close" data-dismiss="modal">&times;</button>
            </div>
            <div class="modal-body" style="padding:20px 24px;" id="emailDetailBody">
                <!-- populated by JS -->
            </div>
            <div class="modal-footer" style="border-top:1px solid var(--et-border);padding:12px 24px;">
                <button type="button" class="et-filter-clear" data-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>

<script src="../assets/js/vendor-all.min.js"></script>
<script src="../assets/plugins/bootstrap/js/bootstrap.min.js"></script>
<script src="../assets/js/pcoded.min.js"></script>

<script>
function viewDetails(e) {
    const statusHtml = e.opened == 1
        ? '<span class="et-badge et-badge-opened"><i class="feather icon-eye"></i> Opened</span>'
        : '<span class="et-badge et-badge-not-opened"><i class="feather icon-eye-off"></i> Not Opened</span>';

    const openedInfo = e.opened == 1
        ? '<div class="et-detail-row"><span class="et-detail-label">Opened At</span><span class="et-detail-value">' + esc(e.opened_at) + '</span></div>'
          + '<div class="et-detail-row"><span class="et-detail-label">User Agent</span><span class="et-detail-value" style="font-size:.78rem;">' + esc(e.user_agent || '—') + '</span></div>'
          + '<div class="et-detail-row"><span class="et-detail-label">IP Address</span><span class="et-detail-value">' + esc(e.ip_address || '—') + '</span></div>'
        : '';

    document.getElementById('emailDetailBody').innerHTML =
        '<div class="et-detail-row"><span class="et-detail-label">Status</span><span class="et-detail-value">' + statusHtml + '</span></div>'
        + '<div class="et-detail-row"><span class="et-detail-label">Recipient</span><span class="et-detail-value">' + esc(e.recipient_email) + '</span></div>'
        + '<div class="et-detail-row"><span class="et-detail-label">Subject</span><span class="et-detail-value">' + esc(e.subject || '—') + '</span></div>'
        + '<div class="et-detail-row"><span class="et-detail-label">Type</span><span class="et-detail-value"><span class="et-badge et-badge-type">' + esc(e.email_type) + '</span></span></div>'
        + '<div class="et-detail-row"><span class="et-detail-label">Sent At</span><span class="et-detail-value">' + esc(e.sent_at) + '</span></div>'
        + openedInfo;

    $('#emailDetailModal').modal('show');
}

function esc(s) {
    if (!s) return '';
    var d = document.createElement('div');
    d.textContent = s;
    return d.innerHTML;
}
</script>

</body>
</html>
