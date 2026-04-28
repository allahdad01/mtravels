<?php
/**
 * SMTP Email Analytics Dashboard for tenant super admins.
 */
session_start();
require_once '../includes/db.php';
require_once '../includes/CommunicationAddonManager.php';

if (!isset($_SESSION['user_id']) || ($_SESSION['role'] ?? '') !== 'tenant_super_admin') {
    header('Location: ../login.php');
    exit();
}

$tenant_id = $_SESSION['tenant_id'] ?? null;
if (!$tenant_id) {
    header('Location: ../login.php');
    exit();
}

$communicationAddonManager = new CommunicationAddonManager($pdo, $tenant_id);
if (!$communicationAddonManager->hasActiveAddon($tenant_id, 'smtp')) {
    $_SESSION['comm_addon_error'] = 'Please purchase the SMTP add-on first.';
    header('Location: request_communication_addon.php');
    exit();
}

function normalizeDate($value, $default)
{
    if (!is_string($value) || $value === '') {
        return $default;
    }

    $date = DateTime::createFromFormat('Y-m-d', $value);
    return $date && $date->format('Y-m-d') === $value ? $value : $default;
}

function tableExists($table)
{
    global $pdo;

    try {
        $stmt = $pdo->prepare('SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = ?');
        $stmt->execute([$table]);
        return (int) $stmt->fetchColumn() > 0;
    } catch (Exception $e) {
        return false;
    }
}

function loadSmtpSettingsSummary($tenant_id)
{
    global $pdo;

    $defaults = [
        'smtp_host' => '',
        'smtp_port' => 587,
        'smtp_encryption' => '',
        'smtp_username' => '',
        'smtp_from_email' => '',
        'smtp_from_name' => '',
        'smtp_enabled' => 0,
    ];

    try {
        $stmt = $pdo->prepare('SELECT smtp_host, smtp_port, smtp_encryption, smtp_username, smtp_from_email, smtp_from_name, smtp_enabled FROM settings WHERE tenant_id = ? LIMIT 1');
        $stmt->execute([$tenant_id]);
        return array_merge($defaults, $stmt->fetch(PDO::FETCH_ASSOC) ?: []);
    } catch (Exception $e) {
        return $defaults;
    }
}

function loadEmailTypes($tenant_id)
{
    global $pdo;

    try {
        $stmt = $pdo->prepare('SELECT DISTINCT email_type FROM email_tracking WHERE tenant_id = ? AND email_type IS NOT NULL AND email_type <> "" ORDER BY email_type ASC');
        $stmt->execute([$tenant_id]);
        return array_values(array_filter($stmt->fetchAll(PDO::FETCH_COLUMN)));
    } catch (Exception $e) {
        return [];
    }
}

function loadBranches($tenant_id)
{
    global $pdo;

    try {
        $stmt = $pdo->prepare("SELECT id, name FROM branches WHERE tenant_id = ? AND status = 'active' ORDER BY name ASC");
        $stmt->execute([$tenant_id]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (Exception $e) {
        return [];
    }
}

function buildEmailWhereClause($tenant_id, $start_date, $end_date, $email_type, $branch_id)
{
    $conditions = ['et.tenant_id = ?', 'DATE(et.sent_at) BETWEEN ? AND ?'];
    $params = [$tenant_id, $start_date, $end_date];

    if ($email_type !== 'all') {
        $conditions[] = 'et.email_type = ?';
        $params[] = $email_type;
    }

    if ($branch_id !== 'all') {
        $conditions[] = 'et.branch_id = ?';
        $params[] = (int) $branch_id;
    }

    return [implode(' AND ', $conditions), $params];
}

function loadEmailAnalytics($tenant_id, $start_date, $end_date, $email_type, $branch_id, $has_email_tracking_table)
{
    global $pdo;

    $defaults = [
        'metrics' => [
            'total_sent' => 0,
            'total_opened' => 0,
            'unopened_count' => 0,
            'unique_recipients' => 0,
            'tracked_types' => 0,
            'tracked_branches' => 0,
            'open_rate' => 0,
        ],
        'type_breakdown' => [],
        'daily_stats' => [],
        'recent_emails' => [],
    ];

    if (!$has_email_tracking_table) {
        return $defaults;
    }

    [$where_clause, $params] = buildEmailWhereClause($tenant_id, $start_date, $end_date, $email_type, $branch_id);

    try {
        $metrics_stmt = $pdo->prepare(
            "SELECT
                COUNT(*) AS total_sent,
                SUM(CASE WHEN opened = 1 THEN 1 ELSE 0 END) AS total_opened,
                SUM(CASE WHEN opened = 0 THEN 1 ELSE 0 END) AS unopened_count,
                COUNT(DISTINCT recipient_email) AS unique_recipients,
                COUNT(DISTINCT email_type) AS tracked_types,
                COUNT(DISTINCT CASE WHEN branch_id IS NOT NULL THEN branch_id END) AS tracked_branches
             FROM email_tracking et
             WHERE {$where_clause}"
        );
        $metrics_stmt->execute($params);
        $metrics = array_merge($defaults['metrics'], $metrics_stmt->fetch(PDO::FETCH_ASSOC) ?: []);
        $metrics['open_rate'] = (int) $metrics['total_sent'] > 0
            ? round(((int) $metrics['total_opened'] / (int) $metrics['total_sent']) * 100, 1)
            : 0;

        $type_stmt = $pdo->prepare(
            "SELECT
                et.email_type,
                COUNT(*) AS total_sent,
                SUM(CASE WHEN opened = 1 THEN 1 ELSE 0 END) AS total_opened,
                ROUND((SUM(CASE WHEN opened = 1 THEN 1 ELSE 0 END) / COUNT(*)) * 100, 1) AS open_rate
             FROM email_tracking et
             WHERE {$where_clause}
             GROUP BY et.email_type
             ORDER BY total_sent DESC, et.email_type ASC"
        );
        $type_stmt->execute($params);
        $type_breakdown = $type_stmt->fetchAll(PDO::FETCH_ASSOC);

        $daily_stmt = $pdo->prepare(
            "SELECT
                DATE(et.sent_at) AS stat_date,
                COUNT(*) AS total_sent,
                SUM(CASE WHEN opened = 1 THEN 1 ELSE 0 END) AS total_opened
             FROM email_tracking et
             WHERE {$where_clause}
             GROUP BY DATE(et.sent_at)
             ORDER BY stat_date ASC"
        );
        $daily_stmt->execute($params);
        $daily_stats = $daily_stmt->fetchAll(PDO::FETCH_ASSOC);

        $recent_stmt = $pdo->prepare(
            "SELECT et.id, et.email_id, et.recipient_email, et.email_type, et.sent_at, et.opened, et.opened_at, et.ip_address, et.user_agent, et.branch_id, b.name AS branch_name
             FROM email_tracking et
             LEFT JOIN branches b ON b.id = et.branch_id
             WHERE {$where_clause}
             ORDER BY et.sent_at DESC
             LIMIT 25"
        );
        $recent_stmt->execute($params);
        $recent_emails = $recent_stmt->fetchAll(PDO::FETCH_ASSOC);

        return [
            'metrics' => $metrics,
            'type_breakdown' => $type_breakdown,
            'daily_stats' => $daily_stats,
            'recent_emails' => $recent_emails,
        ];
    } catch (Exception $e) {
        error_log($e->getMessage());
        return $defaults;
    }
}

$default_start_date = date('Y-m-d', strtotime('-30 days'));
$default_end_date = date('Y-m-d');
$start_date = normalizeDate($_GET['start_date'] ?? '', $default_start_date);
$end_date = normalizeDate($_GET['end_date'] ?? '', $default_end_date);

if ($start_date > $end_date) {
    [$start_date, $end_date] = [$end_date, $start_date];
}

$has_email_tracking_table = tableExists('email_tracking');
$branches = loadBranches($tenant_id);
$email_types = $has_email_tracking_table ? loadEmailTypes($tenant_id) : [];
$email_type = trim((string) ($_GET['email_type'] ?? 'all'));
$allowed_branch_ids = array_map(static function ($branch) {
    return (string) $branch['id'];
}, $branches);
$default_branch = isset($_SESSION['current_branch_id']) ? (string) $_SESSION['current_branch_id'] : 'all';
$branch_id = (string) ($_GET['branch'] ?? $default_branch);

if ($branch_id !== 'all' && !in_array($branch_id, $allowed_branch_ids, true)) {
    $branch_id = 'all';
}

if ($email_type !== 'all' && !in_array($email_type, $email_types, true)) {
    $email_type = 'all';
}

$selected_branch_name = 'All Branches';
foreach ($branches as $branch) {
    if ((string) $branch['id'] === $branch_id) {
        $selected_branch_name = $branch['name'];
        break;
    }
}

$smtp_summary = loadSmtpSettingsSummary($tenant_id);
$analytics = loadEmailAnalytics($tenant_id, $start_date, $end_date, $email_type, $branch_id, $has_email_tracking_table);

include 'header.php';
?>

<style>
@import url('https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&family=JetBrains+Mono:wght@400;600&display=swap');

:root{
    --surface:#f4f7fe;--card-bg:#ffffff;--border:#e8edf5;
    --text-main:#1a2340;--text-sub:#6b7a99;
    --violet:#6d28d9;--pink:#db2777;--blue:#4099ff;
    --green:#16a34a;--amber:#f59e0b;--red:#ef4444;
    --radius:14px;--shadow:0 2px 12px rgba(109,40,217,.08);
}
*,*::before,*::after{box-sizing:border-box}
body,.pcoded-main-container{font-family:'Plus Jakarta Sans',sans-serif!important;background:var(--surface)!important;color:var(--text-main)!important}
.pcoded-content{padding:20px!important}
.page-header{display:none!important}

.dash-header{background:linear-gradient(135deg,#6d28d9 0%,#db2777 100%);border-radius:var(--radius);padding:24px 28px;margin-bottom:24px;display:flex;align-items:center;justify-content:space-between;gap:16px;box-shadow:0 8px 32px rgba(109,40,217,.28);position:relative;overflow:hidden;flex-wrap:wrap}
.dash-header::before{content:'';position:absolute;inset:0;background:url("data:image/svg+xml,%3Csvg width='60' height='60' viewBox='0 0 60 60' xmlns='http://www.w3.org/2000/svg'%3E%3Ccircle cx='30' cy='30' r='20' fill='%23ffffff' fill-opacity='0.05'/%3E%3C/svg%3E") repeat}
.dash-header h4{font-size:22px;font-weight:800;color:#fff;margin:0 0 4px;position:relative}
.dash-header p{color:rgba(255,255,255,.85);margin:0;font-size:13px;position:relative}
.header-actions{display:flex;align-items:center;gap:10px;flex-wrap:wrap;position:relative}
.header-link{display:inline-flex;align-items:center;gap:7px;background:rgba(255,255,255,.18);color:#fff;border:1.5px solid rgba(255,255,255,.32);border-radius:10px;padding:9px 18px;font-size:13px;font-weight:700;text-decoration:none;transition:all .2s}
.header-link:hover{background:rgba(255,255,255,.26);color:#fff;text-decoration:none}
.header-link.secondary{background:#fff;color:#6d28d9}
.header-link.secondary:hover{background:rgba(255,255,255,.92);color:#5b21b6}

.dash-card{background:var(--card-bg);border-radius:var(--radius);border:1px solid var(--border);box-shadow:var(--shadow);overflow:hidden;margin-bottom:20px}
.dash-card-head{padding:15px 20px;border-bottom:1px solid var(--border);display:flex;align-items:center;justify-content:space-between;gap:10px;flex-wrap:wrap}
.dash-card-head-left{display:flex;align-items:center;gap:8px}
.dash-card-head h6{font-size:14px;font-weight:700;margin:0}
.dash-card-body{padding:20px}
.ico{width:28px;height:28px;border-radius:8px;display:flex;align-items:center;justify-content:center;color:#fff;font-size:13px;flex-shrink:0;background:linear-gradient(135deg,#6d28d9 0%,#db2777 100%)}

.filter-grid{display:grid;grid-template-columns:1fr 1fr 1fr 1fr auto;gap:14px;align-items:end}
@media(max-width:1180px){.filter-grid{grid-template-columns:1fr 1fr}}
@media(max-width:640px){.filter-grid{grid-template-columns:1fr}}
.form-group{margin:0}
.form-label{font-size:10px;font-weight:700;text-transform:uppercase;letter-spacing:.5px;color:var(--text-sub);display:block;margin-bottom:6px}
.form-input{width:100%;border:1.5px solid var(--border);border-radius:10px;padding:10px 13px;font-family:inherit;font-size:13px;color:var(--text-main);background:var(--surface);outline:none;transition:border-color .2s,box-shadow .2s}
.form-input:focus{border-color:#6d28d9;background:#fff;box-shadow:0 0 0 3px rgba(109,40,217,.1)}
.filter-actions{display:flex;gap:8px;flex-wrap:wrap}
.apply-btn,.reset-btn{display:inline-flex;align-items:center;justify-content:center;gap:7px;border-radius:10px;padding:10px 18px;font-size:13px;font-weight:700;text-decoration:none;transition:all .2s}
.apply-btn{background:linear-gradient(135deg,#6d28d9 0%,#db2777 100%);color:#fff;border:none;cursor:pointer}
.apply-btn:hover{opacity:.92}
.reset-btn{background:var(--surface);color:var(--text-sub);border:1.5px solid var(--border)}
.reset-btn:hover{border-color:#c6d1e3;color:var(--text-main);text-decoration:none}

.sys-strip{display:grid;grid-template-columns:repeat(6,minmax(0,1fr));gap:12px;margin-bottom:20px}
@media(max-width:1280px){.sys-strip{grid-template-columns:repeat(3,minmax(0,1fr))}}
@media(max-width:700px){.sys-strip{grid-template-columns:repeat(2,minmax(0,1fr))}}
@media(max-width:520px){.sys-strip{grid-template-columns:1fr}}
.sys-item{background:var(--card-bg);border:1px solid var(--border);border-radius:12px;padding:14px 16px;box-shadow:var(--shadow)}
.sys-item-label{font-size:10px;font-weight:700;text-transform:uppercase;letter-spacing:.6px;color:var(--text-sub);display:block;margin-bottom:6px}
.sys-item-value{font-size:14px;font-weight:800;color:var(--text-main)}
.sys-item-value.mono{font-family:'JetBrains Mono',monospace}

.metrics-grid{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:14px;margin-bottom:20px}
@media(max-width:1100px){.metrics-grid{grid-template-columns:repeat(2,minmax(0,1fr))}}
@media(max-width:640px){.metrics-grid{grid-template-columns:1fr}}
.metric-card{background:var(--card-bg);border:1px solid var(--border);border-radius:16px;padding:18px;box-shadow:var(--shadow);position:relative;overflow:hidden}
.metric-card::after{content:'';position:absolute;right:-12px;bottom:-18px;width:70px;height:70px;border-radius:50%;background:rgba(109,40,217,.06)}
.metric-label{font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:.6px;color:var(--text-sub);margin-bottom:8px;position:relative}
.metric-value{font-family:'JetBrains Mono',monospace;font-size:30px;font-weight:800;line-height:1.1;color:var(--text-main);margin-bottom:8px;position:relative}
.metric-sub{font-size:12px;color:var(--text-sub);position:relative}
.metric-strong{color:#6d28d9;font-weight:700}

.charts-grid{display:grid;grid-template-columns:1.6fr 1fr;gap:20px;margin-bottom:20px}
@media(max-width:980px){.charts-grid{grid-template-columns:1fr}}
.chart-shell{position:relative;height:320px}
.empty-note{font-size:12px;color:var(--text-sub);padding-top:10px}

.table-wrap{overflow-x:auto}
.analytics-table{width:100%;border-collapse:collapse}
.analytics-table thead{background:var(--surface)}
.analytics-table th{font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:.6px;color:var(--text-sub);padding:13px 16px;text-align:left;border-bottom:1px solid var(--border);white-space:nowrap}
.analytics-table td{padding:14px 16px;border-bottom:1px solid var(--border);font-size:13px;color:var(--text-main);vertical-align:top}
.analytics-table tbody tr:last-child td{border-bottom:none}
.analytics-table tbody tr:hover{background:#fbfcff}
.mono{font-family:'JetBrains Mono',monospace}
.status-badge{display:inline-flex;align-items:center;border-radius:999px;padding:5px 10px;font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:.4px}
.status-opened{background:rgba(22,163,74,.12);color:#166534}
.status-sent{background:rgba(64,153,255,.12);color:#1d4ed8}
.pill{display:inline-flex;align-items:center;border-radius:999px;padding:4px 10px;font-size:11px;font-weight:700;background:rgba(109,40,217,.08);color:#6d28d9}
.muted{color:var(--text-sub)}
</style>

<div class="pcoded-main-container">
<div class="pcoded-content">

    <div class="dash-header">
        <div>
            <h4><i class="feather icon-mail" style="margin-right:8px;"></i><?= __('email_analytics') ?></h4>
            <p>Track SMTP email volume, open performance, delivery visibility, and recent recipient activity.</p>
        </div>
        <div class="header-actions">
            <a href="tenant_settings.php" class="header-link secondary">
                <i class="feather icon-settings"></i>
                <span>SMTP Settings</span>
            </a>
        </div>
    </div>

    <div class="dash-card">
        <div class="dash-card-head">
            <div class="dash-card-head-left">
                <span class="ico"><i class="feather icon-filter"></i></span>
                <h6>Filters</h6>
            </div>
        </div>
        <div class="dash-card-body">
            <form method="GET" class="filter-grid">
                <div class="form-group">
                    <label class="form-label" for="start_date">Start Date</label>
                    <input type="date" class="form-input" id="start_date" name="start_date" value="<?= htmlspecialchars($start_date) ?>">
                </div>
                <div class="form-group">
                    <label class="form-label" for="end_date">End Date</label>
                    <input type="date" class="form-input" id="end_date" name="end_date" value="<?= htmlspecialchars($end_date) ?>">
                </div>
                <div class="form-group">
                    <label class="form-label" for="email_type">Email Type</label>
                    <select class="form-input" id="email_type" name="email_type">
                        <option value="all" <?= $email_type === 'all' ? 'selected' : '' ?>>All Types</option>
                        <?php foreach ($email_types as $type): ?>
                            <option value="<?= htmlspecialchars($type) ?>" <?= $email_type === $type ? 'selected' : '' ?>><?= htmlspecialchars(ucwords(str_replace('_', ' ', $type))) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label class="form-label" for="branch">Branch</label>
                    <select class="form-input" id="branch" name="branch">
                        <option value="all" <?= $branch_id === 'all' ? 'selected' : '' ?>>All Branches</option>
                        <?php foreach ($branches as $branch): ?>
                            <option value="<?= (int) $branch['id'] ?>" <?= $branch_id === (string) $branch['id'] ? 'selected' : '' ?>><?= htmlspecialchars($branch['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="filter-actions">
                    <button type="submit" class="apply-btn"><i class="feather icon-search"></i>Apply</button>
                    <a href="email_analytics.php" class="reset-btn"><i class="feather icon-refresh-cw"></i>Reset</a>
                </div>
            </form>
        </div>
    </div>

    <div class="sys-strip">
        <div class="sys-item">
            <span class="sys-item-label">SMTP Status</span>
            <span class="sys-item-value"><?= !empty($smtp_summary['smtp_enabled']) ? 'Enabled' : 'Disabled' ?></span>
        </div>
        <div class="sys-item">
            <span class="sys-item-label">SMTP Host</span>
            <span class="sys-item-value mono"><?= htmlspecialchars($smtp_summary['smtp_host'] ?: '--') ?></span>
        </div>
        <div class="sys-item">
            <span class="sys-item-label">Port / Encryption</span>
            <span class="sys-item-value mono"><?= htmlspecialchars((string) ($smtp_summary['smtp_port'] ?: '--')) ?> / <?= htmlspecialchars($smtp_summary['smtp_encryption'] ? strtoupper($smtp_summary['smtp_encryption']) : 'NONE') ?></span>
        </div>
        <div class="sys-item">
            <span class="sys-item-label">From Email</span>
            <span class="sys-item-value mono"><?= htmlspecialchars($smtp_summary['smtp_from_email'] ?: $smtp_summary['smtp_username'] ?: '--') ?></span>
        </div>
        <div class="sys-item">
            <span class="sys-item-label">Sender Name</span>
            <span class="sys-item-value"><?= htmlspecialchars($smtp_summary['smtp_from_name'] ?: '--') ?></span>
        </div>
        <div class="sys-item">
            <span class="sys-item-label">Branch Scope</span>
            <span class="sys-item-value"><?= htmlspecialchars($selected_branch_name) ?></span>
        </div>
    </div>

    <div class="metrics-grid">
        <div class="metric-card">
            <div class="metric-label">Total Sent</div>
            <div class="metric-value"><?= number_format((int) $analytics['metrics']['total_sent']) ?></div>
            <div class="metric-sub">Tracked SMTP emails sent in this period.</div>
        </div>
        <div class="metric-card">
            <div class="metric-label">Total Opened</div>
            <div class="metric-value"><?= number_format((int) $analytics['metrics']['total_opened']) ?></div>
            <div class="metric-sub"><span class="metric-strong"><?= number_format((float) $analytics['metrics']['open_rate'], 1) ?>%</span> open rate.</div>
        </div>
        <div class="metric-card">
            <div class="metric-label">Unopened</div>
            <div class="metric-value"><?= number_format((int) $analytics['metrics']['unopened_count']) ?></div>
            <div class="metric-sub">Tracked emails without an open event.</div>
        </div>
        <div class="metric-card">
            <div class="metric-label">Unique Recipients</div>
            <div class="metric-value"><?= number_format((int) $analytics['metrics']['unique_recipients']) ?></div>
            <div class="metric-sub"><?= number_format((int) $analytics['metrics']['tracked_types']) ?> email types across <?= number_format((int) $analytics['metrics']['tracked_branches']) ?> branches.</div>
        </div>
    </div>

    <div class="charts-grid">
        <div class="dash-card" style="margin-bottom:0;">
            <div class="dash-card-head">
                <div class="dash-card-head-left">
                    <span class="ico"><i class="feather icon-activity"></i></span>
                    <h6>Daily Email Trend</h6>
                </div>
            </div>
            <div class="dash-card-body">
                <div class="chart-shell">
                    <canvas id="dailyEmailChart"></canvas>
                </div>
                <?php if (empty($analytics['daily_stats'])): ?>
                    <div class="empty-note">No email activity was recorded for the selected range.</div>
                <?php endif; ?>
            </div>
        </div>

        <div class="dash-card" style="margin-bottom:0;">
            <div class="dash-card-head">
                <div class="dash-card-head-left">
                    <span class="ico"><i class="feather icon-pie-chart"></i></span>
                    <h6>Emails by Type</h6>
                </div>
            </div>
            <div class="dash-card-body">
                <div class="chart-shell">
                    <canvas id="typeChart"></canvas>
                </div>
                <?php if (empty($analytics['type_breakdown'])): ?>
                    <div class="empty-note">Type distribution appears once tracked emails exist for the current filters.</div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <div class="dash-card">
        <div class="dash-card-head">
            <div class="dash-card-head-left">
                <span class="ico"><i class="feather icon-layers"></i></span>
                <h6>Email Type Performance</h6>
            </div>
            <span class="pill"><?= $email_type === 'all' ? 'All email types' : htmlspecialchars(ucwords(str_replace('_', ' ', $email_type))) ?></span>
        </div>
        <div class="dash-card-body" style="padding:0;">
            <div class="table-wrap">
                <table class="analytics-table">
                    <thead>
                        <tr>
                            <th>Type</th>
                            <th>Sent</th>
                            <th>Opened</th>
                            <th>Open Rate</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (!empty($analytics['type_breakdown'])): ?>
                            <?php foreach ($analytics['type_breakdown'] as $row): ?>
                                <tr>
                                    <td><?= htmlspecialchars(ucwords(str_replace('_', ' ', $row['email_type']))) ?></td>
                                    <td class="mono"><?= number_format((int) $row['total_sent']) ?></td>
                                    <td class="mono"><?= number_format((int) $row['total_opened']) ?></td>
                                    <td class="mono"><?= number_format((float) ($row['open_rate'] ?? 0), 1) ?>%</td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="4" class="muted">No tracked SMTP emails were found for the current filters.</td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <div class="dash-card">
        <div class="dash-card-head">
            <div class="dash-card-head-left">
                <span class="ico"><i class="feather icon-clock"></i></span>
                <h6>Recent Email Activity</h6>
            </div>
            <span class="pill">Latest 25 records</span>
        </div>
        <div class="dash-card-body" style="padding:0;">
            <div class="table-wrap">
                <table class="analytics-table">
                    <thead>
                        <tr>
                            <th>Sent At</th>
                            <th>Branch</th>
                            <th>Recipient</th>
                            <th>Type</th>
                            <th>Status</th>
                            <th>Opened At</th>
                            <th>IP Address</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (!empty($analytics['recent_emails'])): ?>
                            <?php foreach ($analytics['recent_emails'] as $email): ?>
                                <tr>
                                    <td class="mono"><?= htmlspecialchars(date('M d, Y H:i', strtotime($email['sent_at']))) ?></td>
                                    <td><?= htmlspecialchars($email['branch_name'] ?: 'No Branch') ?></td>
                                    <td><?= htmlspecialchars($email['recipient_email']) ?></td>
                                    <td><?= htmlspecialchars(ucwords(str_replace('_', ' ', $email['email_type']))) ?></td>
                                    <td>
                                        <?php if (!empty($email['opened'])): ?>
                                            <span class="status-badge status-opened">Opened</span>
                                        <?php else: ?>
                                            <span class="status-badge status-sent">Sent</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="mono">
                                        <?php if (!empty($email['opened_at'])): ?>
                                            <?= htmlspecialchars(date('M d, Y H:i', strtotime($email['opened_at']))) ?>
                                        <?php else: ?>
                                            <span class="muted">-</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="mono"><?= htmlspecialchars($email['ip_address'] ?: '-') ?></td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="7" class="muted">No recent SMTP activity is available for the selected range.</td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

</div>
</div>

<?php include 'footer.php'; ?>

<script src="https://cdn.jsdelivr.net/npm/chart.js@3.9.1/dist/chart.min.js"></script>
<script>
const dailyStats = <?= json_encode($analytics['daily_stats'], JSON_UNESCAPED_SLASHES) ?>;
const typeBreakdown = <?= json_encode($analytics['type_breakdown'], JSON_UNESCAPED_SLASHES) ?>;

Chart.defaults.font.family = "'Plus Jakarta Sans', sans-serif";
Chart.defaults.color = '#6b7a99';

new Chart(document.getElementById('dailyEmailChart'), {
    type: 'line',
    data: {
        labels: dailyStats.map(item => item.stat_date),
        datasets: [
            {
                label: 'Emails Sent',
                data: dailyStats.map(item => Number(item.total_sent)),
                borderColor: '#6d28d9',
                backgroundColor: 'rgba(109, 40, 217, 0.12)',
                fill: true,
                tension: 0.35,
                borderWidth: 2
            },
            {
                label: 'Emails Opened',
                data: dailyStats.map(item => Number(item.total_opened)),
                borderColor: '#db2777',
                backgroundColor: 'rgba(219, 39, 119, 0.08)',
                tension: 0.35,
                borderWidth: 2
            }
        ]
    },
    options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: {
            legend: {
                position: 'bottom'
            }
        },
        scales: {
            y: {
                beginAtZero: true,
                ticks: {
                    precision: 0
                },
                grid: {
                    color: 'rgba(107, 122, 153, 0.12)'
                }
            },
            x: {
                grid: {
                    display: false
                }
            }
        }
    }
});

new Chart(document.getElementById('typeChart'), {
    type: 'doughnut',
    data: {
        labels: typeBreakdown.map(item => item.email_type ? item.email_type.replace(/_/g, ' ') : 'unknown'),
        datasets: [{
            data: typeBreakdown.map(item => Number(item.total_sent)),
            backgroundColor: ['#6d28d9', '#db2777', '#4099ff', '#f59e0b', '#16a34a', '#ef4444'],
            borderWidth: 0
        }]
    },
    options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: {
            legend: {
                position: 'bottom'
            }
        }
    }
});
</script>
