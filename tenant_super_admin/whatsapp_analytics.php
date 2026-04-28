<?php
/**
 * WhatsApp Analytics Dashboard for tenant super admins.
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
if (!$communicationAddonManager->hasActiveAddon($tenant_id, 'whatsapp')) {
    $_SESSION['comm_addon_error'] = 'Please purchase the WhatsApp add-on first.';
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
        $stmt = $pdo->prepare('SHOW TABLES LIKE ?');
        $stmt->execute([$table]);
        return (bool) $stmt->fetchColumn();
    } catch (Exception $e) {
        return false;
    }
}

function loadWhatsAppSettingsSummary($tenant_id)
{
    global $pdo;

    $defaults = [
        'provider' => 'meta',
        'status' => 'inactive',
        'auto_notifications' => 1,
        'real_time_notifications' => 0,
        'max_messages_per_hour' => 1000,
        'retry_attempts' => 3,
        'updated_at' => null,
    ];

    try {
        $stmt = $pdo->prepare('SELECT provider, status, auto_notifications, real_time_notifications, max_messages_per_hour, retry_attempts, updated_at FROM whatsapp_settings WHERE tenant_id = ? LIMIT 1');
        $stmt->execute([$tenant_id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];

        return array_merge($defaults, $row);
    } catch (Exception $e) {
        return $defaults;
    }
}

function loadMessageTypes($tenant_id)
{
    global $pdo;

    try {
        $stmt = $pdo->prepare('SELECT DISTINCT message_type FROM whatsapp_messages WHERE tenant_id = ? ORDER BY message_type ASC');
        $stmt->execute([$tenant_id]);
        return array_values(array_filter($stmt->fetchAll(PDO::FETCH_COLUMN)));
    } catch (Exception $e) {
        return [];
    }
}

function buildMessageWhereClause($tenant_id, $start_date, $end_date, $message_type)
{
    $conditions = ['wm.tenant_id = ?', 'wm.created_at BETWEEN ? AND ?'];
    $params = [$tenant_id, $start_date . ' 00:00:00', $end_date . ' 23:59:59'];

    if ($message_type !== 'all') {
        $conditions[] = 'wm.message_type = ?';
        $params[] = $message_type;
    }

    return [implode(' AND ', $conditions), $params];
}

function loadTrackedAnalyticsSummary($tenant_id, $start_date, $end_date, $message_type, $has_analytics_table)
{
    global $pdo;

    if (!$has_analytics_table) {
        return [];
    }

    $conditions = ['tenant_id = ?', 'date BETWEEN ? AND ?'];
    $params = [$tenant_id, $start_date, $end_date];

    if ($message_type !== 'all') {
        $conditions[] = 'message_type = ?';
        $params[] = $message_type;
    }

    try {
        $stmt = $pdo->prepare(
            'SELECT
                COALESCE(SUM(total_sent), 0) AS total_sent,
                COALESCE(SUM(total_delivered), 0) AS total_delivered,
                COALESCE(SUM(total_failed), 0) AS total_failed,
                COALESCE(SUM(total_read), 0) AS total_read,
                ROUND(AVG(delivery_rate), 1) AS avg_delivery_rate,
                ROUND(AVG(read_rate), 1) AS avg_read_rate
             FROM whatsapp_analytics
             WHERE ' . implode(' AND ', $conditions)
        );
        $stmt->execute($params);
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: [];
    } catch (Exception $e) {
        return [];
    }
}

function loadWhatsAppAnalytics($tenant_id, $start_date, $end_date, $message_type, $has_delivery_status_table)
{
    global $pdo;

    [$where_clause, $params] = buildMessageWhereClause($tenant_id, $start_date, $end_date, $message_type);
    $join_delivery = $has_delivery_status_table ? ' LEFT JOIN whatsapp_delivery_status wds ON wds.message_id = wm.id ' : '';
    $read_count_sql = $has_delivery_status_table
        ? "COALESCE(SUM(CASE WHEN wds.status = 'read' THEN 1 ELSE 0 END), 0)"
        : '0';
    $delivery_event_sql = $has_delivery_status_table
        ? "COALESCE(wds.status, '') AS delivery_event"
        : "'' AS delivery_event";

    $defaults = [
        'metrics' => [
            'total_messages' => 0,
            'unique_recipients' => 0,
            'pending_count' => 0,
            'sent_count' => 0,
            'delivered_count' => 0,
            'failed_count' => 0,
            'expired_count' => 0,
            'processed_count' => 0,
            'read_count' => 0,
            'avg_queue_minutes' => 0,
            'delivery_rate' => 0,
            'read_rate' => 0,
            'success_rate' => 0,
        ],
        'type_breakdown' => [],
        'daily_stats' => [],
        'recent_messages' => [],
    ];

    try {
        $metrics_sql = "
            SELECT
                COUNT(*) AS total_messages,
                COUNT(DISTINCT wm.phone_number) AS unique_recipients,
                SUM(CASE WHEN wm.status = 'pending' THEN 1 ELSE 0 END) AS pending_count,
                SUM(CASE WHEN wm.status = 'sent' THEN 1 ELSE 0 END) AS sent_count,
                SUM(CASE WHEN wm.status = 'delivered' THEN 1 ELSE 0 END) AS delivered_count,
                SUM(CASE WHEN wm.status = 'failed' THEN 1 ELSE 0 END) AS failed_count,
                SUM(CASE WHEN wm.status = 'expired' THEN 1 ELSE 0 END) AS expired_count,
                SUM(CASE WHEN wm.status <> 'pending' THEN 1 ELSE 0 END) AS processed_count,
                {$read_count_sql} AS read_count,
                ROUND(AVG(CASE WHEN wm.sent_at IS NOT NULL THEN TIMESTAMPDIFF(MINUTE, wm.created_at, wm.sent_at) END), 1) AS avg_queue_minutes
            FROM whatsapp_messages wm
            {$join_delivery}
            WHERE {$where_clause}
        ";
        $metrics_stmt = $pdo->prepare($metrics_sql);
        $metrics_stmt->execute($params);
        $metrics = array_merge($defaults['metrics'], $metrics_stmt->fetch(PDO::FETCH_ASSOC) ?: []);

        $type_sql = "
            SELECT
                wm.message_type,
                COUNT(*) AS total_messages,
                SUM(CASE WHEN wm.status = 'delivered' THEN 1 ELSE 0 END) AS delivered_count,
                SUM(CASE WHEN wm.status = 'failed' THEN 1 ELSE 0 END) AS failed_count,
                SUM(CASE WHEN wm.status = 'pending' THEN 1 ELSE 0 END) AS pending_count,
                {$read_count_sql} AS read_count
            FROM whatsapp_messages wm
            {$join_delivery}
            WHERE {$where_clause}
            GROUP BY wm.message_type
            ORDER BY total_messages DESC, wm.message_type ASC
        ";
        $type_stmt = $pdo->prepare($type_sql);
        $type_stmt->execute($params);
        $type_breakdown = $type_stmt->fetchAll(PDO::FETCH_ASSOC);

        $daily_sql = "
            SELECT
                DATE(wm.created_at) AS stat_date,
                COUNT(*) AS total_messages,
                SUM(CASE WHEN wm.status = 'delivered' THEN 1 ELSE 0 END) AS delivered_count,
                SUM(CASE WHEN wm.status = 'failed' THEN 1 ELSE 0 END) AS failed_count,
                SUM(CASE WHEN wm.status = 'pending' THEN 1 ELSE 0 END) AS pending_count,
                {$read_count_sql} AS read_count
            FROM whatsapp_messages wm
            {$join_delivery}
            WHERE {$where_clause}
            GROUP BY DATE(wm.created_at)
            ORDER BY stat_date ASC
        ";
        $daily_stmt = $pdo->prepare($daily_sql);
        $daily_stmt->execute($params);
        $daily_stats = $daily_stmt->fetchAll(PDO::FETCH_ASSOC);

        $recent_sql = "
            SELECT
                wm.id,
                wm.phone_number,
                wm.message_type,
                wm.message,
                wm.status,
                wm.retry_count,
                wm.error_message,
                wm.created_at,
                wm.sent_at,
                wm.delivered_at,
                wm.failed_at,
                {$delivery_event_sql}
            FROM whatsapp_messages wm
            {$join_delivery}
            WHERE {$where_clause}
            ORDER BY wm.created_at DESC
            LIMIT 20
        ";
        $recent_stmt = $pdo->prepare($recent_sql);
        $recent_stmt->execute($params);
        $recent_messages = $recent_stmt->fetchAll(PDO::FETCH_ASSOC);

        $processed_count = (int) $metrics['processed_count'];
        $delivered_count = (int) $metrics['delivered_count'];
        $read_count = (int) $metrics['read_count'];
        $successful_count = (int) $metrics['sent_count'] + $delivered_count;

        $metrics['delivery_rate'] = $processed_count > 0 ? round(($delivered_count / $processed_count) * 100, 1) : 0;
        $metrics['read_rate'] = $delivered_count > 0 ? round(($read_count / $delivered_count) * 100, 1) : 0;
        $metrics['success_rate'] = $processed_count > 0 ? round(($successful_count / $processed_count) * 100, 1) : 0;
        $metrics['avg_queue_minutes'] = $metrics['avg_queue_minutes'] !== null ? (float) $metrics['avg_queue_minutes'] : 0;

        return [
            'metrics' => $metrics,
            'type_breakdown' => $type_breakdown,
            'daily_stats' => $daily_stats,
            'recent_messages' => $recent_messages,
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

$message_type = trim((string) ($_GET['message_type'] ?? 'all'));
$has_delivery_status_table = tableExists('whatsapp_delivery_status');
$has_analytics_table = tableExists('whatsapp_analytics');
$message_types = loadMessageTypes($tenant_id);

if ($message_type !== 'all' && !in_array($message_type, $message_types, true)) {
    $message_type = 'all';
}

$settings_summary = loadWhatsAppSettingsSummary($tenant_id);
$analytics = loadWhatsAppAnalytics($tenant_id, $start_date, $end_date, $message_type, $has_delivery_status_table);
$tracked_summary = loadTrackedAnalyticsSummary($tenant_id, $start_date, $end_date, $message_type, $has_analytics_table);

include 'header.php';
?>

<style>
@import url('https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&family=JetBrains+Mono:wght@400;600&display=swap');

:root{
    --surface:#f4f7fe;--card-bg:#ffffff;--border:#e8edf5;
    --text-main:#1a2340;--text-sub:#6b7a99;
    --green:#25d366;--green-dark:#128c7e;--blue:#4099ff;
    --teal:#2ed8b6;--amber:#f59e0b;--red:#ef4444;
    --radius:14px;--shadow:0 2px 12px rgba(37,211,102,.08);
}
*,*::before,*::after{box-sizing:border-box}
body,.pcoded-main-container{font-family:'Plus Jakarta Sans',sans-serif!important;background:var(--surface)!important;color:var(--text-main)!important}
.pcoded-content{padding:20px!important}
.page-header{display:none!important}

.dash-header{background:linear-gradient(135deg,#25d366 0%,#128c7e 100%);border-radius:var(--radius);padding:24px 28px;margin-bottom:24px;display:flex;align-items:center;justify-content:space-between;gap:16px;box-shadow:0 8px 32px rgba(37,211,102,.28);position:relative;overflow:hidden;flex-wrap:wrap}
.dash-header::before{content:'';position:absolute;inset:0;background:url("data:image/svg+xml,%3Csvg width='60' height='60' viewBox='0 0 60 60' xmlns='http://www.w3.org/2000/svg'%3E%3Ccircle cx='30' cy='30' r='20' fill='%23ffffff' fill-opacity='0.05'/%3E%3C/svg%3E") repeat}
.dash-header h4{font-size:22px;font-weight:800;color:#fff;margin:0 0 4px;position:relative}
.dash-header p{color:rgba(255,255,255,.85);margin:0;font-size:13px;position:relative}
.header-actions{display:flex;align-items:center;gap:10px;flex-wrap:wrap;position:relative}
.header-link{display:inline-flex;align-items:center;gap:7px;background:rgba(255,255,255,.18);color:#fff;border:1.5px solid rgba(255,255,255,.32);border-radius:10px;padding:9px 18px;font-size:13px;font-weight:700;text-decoration:none;transition:all .2s}
.header-link:hover{background:rgba(255,255,255,.26);color:#fff;text-decoration:none}
.header-link.secondary{background:#fff;color:#128c7e}
.header-link.secondary:hover{background:rgba(255,255,255,.92);color:#0f766e}

.dash-card{background:var(--card-bg);border-radius:var(--radius);border:1px solid var(--border);box-shadow:var(--shadow);overflow:hidden;margin-bottom:20px}
.dash-card-head{padding:15px 20px;border-bottom:1px solid var(--border);display:flex;align-items:center;justify-content:space-between;gap:10px;flex-wrap:wrap}
.dash-card-head-left{display:flex;align-items:center;gap:8px}
.dash-card-head h6{font-size:14px;font-weight:700;margin:0}
.dash-card-body{padding:20px}
.ico{width:28px;height:28px;border-radius:8px;display:flex;align-items:center;justify-content:center;color:#fff;font-size:13px;flex-shrink:0;background:linear-gradient(135deg,#25d366 0%,#128c7e 100%)}

.filter-grid{display:grid;grid-template-columns:1fr 1fr 1fr auto;gap:14px;align-items:end}
@media(max-width:960px){.filter-grid{grid-template-columns:1fr 1fr}}
@media(max-width:640px){.filter-grid{grid-template-columns:1fr}}
.form-group{margin:0}
.form-label{font-size:10px;font-weight:700;text-transform:uppercase;letter-spacing:.5px;color:var(--text-sub);display:block;margin-bottom:6px}
.form-input{width:100%;border:1.5px solid var(--border);border-radius:10px;padding:10px 13px;font-family:inherit;font-size:13px;color:var(--text-main);background:var(--surface);outline:none;transition:border-color .2s,box-shadow .2s}
.form-input:focus{border-color:#25d366;background:#fff;box-shadow:0 0 0 3px rgba(37,211,102,.1)}
.filter-actions{display:flex;gap:8px;flex-wrap:wrap}
.apply-btn,.reset-btn{display:inline-flex;align-items:center;justify-content:center;gap:7px;border-radius:10px;padding:10px 18px;font-size:13px;font-weight:700;text-decoration:none;transition:all .2s}
.apply-btn{background:linear-gradient(135deg,#25d366 0%,#128c7e 100%);color:#fff;border:none;cursor:pointer}
.apply-btn:hover{opacity:.92}
.reset-btn{background:var(--surface);color:var(--text-sub);border:1.5px solid var(--border)}
.reset-btn:hover{border-color:#c6d1e3;color:var(--text-main);text-decoration:none}

.sys-strip{display:grid;grid-template-columns:repeat(5,minmax(0,1fr));gap:12px;margin-bottom:20px}
@media(max-width:1100px){.sys-strip{grid-template-columns:repeat(3,minmax(0,1fr))}}
@media(max-width:700px){.sys-strip{grid-template-columns:repeat(2,minmax(0,1fr))}}
@media(max-width:520px){.sys-strip{grid-template-columns:1fr}}
.sys-item{background:var(--card-bg);border:1px solid var(--border);border-radius:12px;padding:14px 16px;box-shadow:var(--shadow)}
.sys-item-label{font-size:10px;font-weight:700;text-transform:uppercase;letter-spacing:.6px;color:var(--text-sub);display:block;margin-bottom:6px}
.sys-item-value{font-size:14px;font-weight:800;color:var(--text-main)}
.sys-item-value.mono{font-family:'JetBrains Mono',monospace}

.metrics-grid{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:14px;margin-bottom:20px}
.metrics-grid.secondary{margin-top:-4px}
@media(max-width:1100px){.metrics-grid{grid-template-columns:repeat(2,minmax(0,1fr))}}
@media(max-width:640px){.metrics-grid{grid-template-columns:1fr}}
.metric-card{background:var(--card-bg);border:1px solid var(--border);border-radius:16px;padding:18px;box-shadow:var(--shadow);position:relative;overflow:hidden}
.metric-card::after{content:'';position:absolute;right:-12px;bottom:-18px;width:70px;height:70px;border-radius:50%;background:rgba(37,211,102,.06)}
.metric-label{font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:.6px;color:var(--text-sub);margin-bottom:8px;position:relative}
.metric-value{font-family:'JetBrains Mono',monospace;font-size:30px;font-weight:800;line-height:1.1;color:var(--text-main);margin-bottom:8px;position:relative}
.metric-sub{font-size:12px;color:var(--text-sub);position:relative}
.metric-strong{color:#128c7e;font-weight:700}

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
.status-pending{background:rgba(245,158,11,.12);color:#92400e}
.status-sent{background:rgba(64,153,255,.12);color:#1d4ed8}
.status-delivered{background:rgba(37,211,102,.12);color:#166534}
.status-failed{background:rgba(239,68,68,.1);color:#991b1b}
.status-expired{background:rgba(107,122,153,.12);color:#475569}
.pill{display:inline-flex;align-items:center;border-radius:999px;padding:4px 10px;font-size:11px;font-weight:700;background:rgba(18,140,126,.08);color:#128c7e}
.message-preview{max-width:320px;line-height:1.45;color:var(--text-sub)}
.muted{color:var(--text-sub)}
</style>

<div class="pcoded-main-container">
<div class="pcoded-content">

    <div class="dash-header">
        <div>
            <h4><i class="feather icon-bar-chart-2" style="margin-right:8px;"></i>WhatsApp Analytics</h4>
            <p>Track message volume, delivery performance, queue health, and recent WhatsApp activity.</p>
        </div>
        <div class="header-actions">
            <a href="whatsapp_settings.php" class="header-link secondary">
                <i class="feather icon-settings"></i>
                <span>Open Settings</span>
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
                    <label class="form-label" for="message_type">Message Type</label>
                    <select class="form-input" id="message_type" name="message_type">
                        <option value="all" <?= $message_type === 'all' ? 'selected' : '' ?>>All Types</option>
                        <?php foreach ($message_types as $type): ?>
                            <option value="<?= htmlspecialchars($type) ?>" <?= $message_type === $type ? 'selected' : '' ?>><?= htmlspecialchars(ucwords(str_replace('_', ' ', $type))) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="filter-actions">
                    <button type="submit" class="apply-btn"><i class="feather icon-search"></i>Apply</button>
                    <a href="whatsapp_analytics.php" class="reset-btn"><i class="feather icon-refresh-cw"></i>Reset</a>
                </div>
            </form>
        </div>
    </div>

    <div class="sys-strip">
        <div class="sys-item">
            <span class="sys-item-label">Provider</span>
            <span class="sys-item-value"><?= htmlspecialchars(ucfirst($settings_summary['provider'])) ?></span>
        </div>
        <div class="sys-item">
            <span class="sys-item-label">Configuration Status</span>
            <span class="sys-item-value"><?= htmlspecialchars(ucfirst($settings_summary['status'])) ?></span>
        </div>
        <div class="sys-item">
            <span class="sys-item-label">Automation</span>
            <span class="sys-item-value"><?= !empty($settings_summary['auto_notifications']) ? 'Enabled' : 'Disabled' ?></span>
        </div>
        <div class="sys-item">
            <span class="sys-item-label">Messages / Hour</span>
            <span class="sys-item-value mono"><?= number_format((int) $settings_summary['max_messages_per_hour']) ?></span>
        </div>
        <div class="sys-item">
            <span class="sys-item-label">Tracked Read Rate</span>
            <span class="sys-item-value mono"><?= isset($tracked_summary['avg_read_rate']) && $tracked_summary['avg_read_rate'] !== null ? number_format((float) $tracked_summary['avg_read_rate'], 1) . '%' : '--' ?></span>
        </div>
    </div>

    <div class="metrics-grid">
        <div class="metric-card">
            <div class="metric-label">Total Messages</div>
            <div class="metric-value"><?= number_format((int) $analytics['metrics']['total_messages']) ?></div>
            <div class="metric-sub">Messages created in the selected period.</div>
        </div>
        <div class="metric-card">
            <div class="metric-label">Unique Recipients</div>
            <div class="metric-value"><?= number_format((int) $analytics['metrics']['unique_recipients']) ?></div>
            <div class="metric-sub">Distinct phone numbers reached.</div>
        </div>
        <div class="metric-card">
            <div class="metric-label">Delivered</div>
            <div class="metric-value"><?= number_format((int) $analytics['metrics']['delivered_count']) ?></div>
            <div class="metric-sub"><span class="metric-strong"><?= number_format((float) $analytics['metrics']['delivery_rate'], 1) ?>%</span> of processed messages.</div>
        </div>
        <div class="metric-card">
            <div class="metric-label">Failed</div>
            <div class="metric-value"><?= number_format((int) $analytics['metrics']['failed_count']) ?></div>
            <div class="metric-sub">Failed or rejected message sends.</div>
        </div>
    </div>

    <div class="metrics-grid secondary">
        <div class="metric-card">
            <div class="metric-label">Pending Queue</div>
            <div class="metric-value"><?= number_format((int) $analytics['metrics']['pending_count']) ?></div>
            <div class="metric-sub">Waiting to be processed.</div>
        </div>
        <div class="metric-card">
            <div class="metric-label">Sent To Provider</div>
            <div class="metric-value"><?= number_format((int) $analytics['metrics']['sent_count']) ?></div>
            <div class="metric-sub"><span class="metric-strong"><?= number_format((float) $analytics['metrics']['success_rate'], 1) ?>%</span> processed successfully.</div>
        </div>
        <div class="metric-card">
            <div class="metric-label">Read Confirmations</div>
            <div class="metric-value"><?= number_format((int) $analytics['metrics']['read_count']) ?></div>
            <div class="metric-sub"><span class="metric-strong"><?= number_format((float) $analytics['metrics']['read_rate'], 1) ?>%</span> of delivered messages<?= $has_delivery_status_table ? '' : ' (webhook tracking unavailable)' ?>.</div>
        </div>
        <div class="metric-card">
            <div class="metric-label">Avg Queue Time</div>
            <div class="metric-value"><?= number_format((float) $analytics['metrics']['avg_queue_minutes'], 1) ?></div>
            <div class="metric-sub">Minutes from creation to send.</div>
        </div>
    </div>

    <div class="charts-grid">
        <div class="dash-card" style="margin-bottom:0;">
            <div class="dash-card-head">
                <div class="dash-card-head-left">
                    <span class="ico"><i class="feather icon-activity"></i></span>
                    <h6>Daily Messaging Trend</h6>
                </div>
            </div>
            <div class="dash-card-body">
                <div class="chart-shell">
                    <canvas id="dailyTrendChart"></canvas>
                </div>
                <?php if (empty($analytics['daily_stats'])): ?>
                    <div class="empty-note">No message activity was recorded for the selected range.</div>
                <?php endif; ?>
            </div>
        </div>

        <div class="dash-card" style="margin-bottom:0;">
            <div class="dash-card-head">
                <div class="dash-card-head-left">
                    <span class="ico"><i class="feather icon-pie-chart"></i></span>
                    <h6>Status Breakdown</h6>
                </div>
            </div>
            <div class="dash-card-body">
                <div class="chart-shell">
                    <canvas id="statusChart"></canvas>
                </div>
                <?php if ((int) $analytics['metrics']['total_messages'] === 0): ?>
                    <div class="empty-note">Status distribution will appear once messages exist in the selected period.</div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <div class="dash-card">
        <div class="dash-card-head">
            <div class="dash-card-head-left">
                <span class="ico"><i class="feather icon-layers"></i></span>
                <h6>Message Type Performance</h6>
            </div>
            <span class="pill"><?= $message_type === 'all' ? 'All message types' : htmlspecialchars(ucwords(str_replace('_', ' ', $message_type))) ?></span>
        </div>
        <div class="dash-card-body" style="padding:0;">
            <div class="table-wrap">
                <table class="analytics-table">
                    <thead>
                        <tr>
                            <th>Type</th>
                            <th>Total</th>
                            <th>Delivered</th>
                            <th>Failed</th>
                            <th>Pending</th>
                            <th>Read</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (!empty($analytics['type_breakdown'])): ?>
                            <?php foreach ($analytics['type_breakdown'] as $row): ?>
                                <tr>
                                    <td><?= htmlspecialchars(ucwords(str_replace('_', ' ', $row['message_type']))) ?></td>
                                    <td class="mono"><?= number_format((int) $row['total_messages']) ?></td>
                                    <td class="mono"><?= number_format((int) $row['delivered_count']) ?></td>
                                    <td class="mono"><?= number_format((int) $row['failed_count']) ?></td>
                                    <td class="mono"><?= number_format((int) $row['pending_count']) ?></td>
                                    <td class="mono"><?= number_format((int) $row['read_count']) ?></td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="6" class="muted">No WhatsApp messages were found for the current filters.</td>
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
                <h6>Recent Message Activity</h6>
            </div>
            <span class="pill">Latest 20 records</span>
        </div>
        <div class="dash-card-body" style="padding:0;">
            <div class="table-wrap">
                <table class="analytics-table">
                    <thead>
                        <tr>
                            <th>Created</th>
                            <th>Recipient</th>
                            <th>Type</th>
                            <th>Status</th>
                            <th>Delivery Event</th>
                            <th>Message</th>
                            <th>Retries</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (!empty($analytics['recent_messages'])): ?>
                            <?php foreach ($analytics['recent_messages'] as $message): ?>
                                <?php $status_class = 'status-' . strtolower($message['status']); ?>
                                <tr>
                                    <td class="mono"><?= htmlspecialchars(date('M d, Y H:i', strtotime($message['created_at']))) ?></td>
                                    <td class="mono"><?= htmlspecialchars($message['phone_number']) ?></td>
                                    <td><?= htmlspecialchars(ucwords(str_replace('_', ' ', $message['message_type']))) ?></td>
                                    <td><span class="status-badge <?= htmlspecialchars($status_class) ?>"><?= htmlspecialchars($message['status']) ?></span></td>
                                    <td>
                                        <?php if ($message['delivery_event'] !== ''): ?>
                                            <?= htmlspecialchars(ucfirst($message['delivery_event'])) ?>
                                        <?php else: ?>
                                            <span class="muted">-</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <div class="message-preview"><?= htmlspecialchars(mb_strimwidth(trim(preg_replace('/\s+/', ' ', $message['message'])), 0, 110, '...')) ?></div>
                                        <?php if (!empty($message['error_message'])): ?>
                                            <div class="muted" style="margin-top:6px;">Error: <?= htmlspecialchars($message['error_message']) ?></div>
                                        <?php endif; ?>
                                    </td>
                                    <td class="mono"><?= number_format((int) $message['retry_count']) ?></td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="7" class="muted">No recent WhatsApp activity is available for the selected range.</td>
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

const statusTotals = {
    pending: <?= (int) $analytics['metrics']['pending_count'] ?>,
    sent: <?= (int) $analytics['metrics']['sent_count'] ?>,
    delivered: <?= (int) $analytics['metrics']['delivered_count'] ?>,
    failed: <?= (int) $analytics['metrics']['failed_count'] ?>,
    expired: <?= (int) $analytics['metrics']['expired_count'] ?>,
};

Chart.defaults.font.family = "'Plus Jakarta Sans', sans-serif";
Chart.defaults.color = '#6b7a99';

new Chart(document.getElementById('dailyTrendChart'), {
    type: 'line',
    data: {
        labels: dailyStats.map(item => item.stat_date),
        datasets: [
            {
                label: 'Total Messages',
                data: dailyStats.map(item => Number(item.total_messages)),
                borderColor: '#25d366',
                backgroundColor: 'rgba(37, 211, 102, 0.12)',
                fill: true,
                tension: 0.35,
                borderWidth: 2
            },
            {
                label: 'Delivered',
                data: dailyStats.map(item => Number(item.delivered_count)),
                borderColor: '#128c7e',
                backgroundColor: 'rgba(18, 140, 126, 0.08)',
                tension: 0.35,
                borderWidth: 2
            },
            {
                label: 'Failed',
                data: dailyStats.map(item => Number(item.failed_count)),
                borderColor: '#ef4444',
                backgroundColor: 'rgba(239, 68, 68, 0.08)',
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

new Chart(document.getElementById('statusChart'), {
    type: 'doughnut',
    data: {
        labels: ['Pending', 'Sent', 'Delivered', 'Failed', 'Expired'],
        datasets: [{
            data: [statusTotals.pending, statusTotals.sent, statusTotals.delivered, statusTotals.failed, statusTotals.expired],
            backgroundColor: ['#f59e0b', '#4099ff', '#25d366', '#ef4444', '#94a3b8'],
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
