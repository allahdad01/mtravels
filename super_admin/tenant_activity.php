<?php
require_once 'security.php';
if (!isset($_SESSION['user_id']) || !isset($_SESSION['role']) || $_SESSION['role'] !== 'super_admin' || !is_null($_SESSION['tenant_id'])) {
    header('Location: ../login.php');
    exit();
}
require_once '../includes/db.php';

$user_id = $_SESSION['user_id'];
$stmt = $pdo->prepare("SELECT email, name, profile_pic, created_at FROM users WHERE id = ? AND role = 'super_admin'");
$stmt->execute([$user_id]);
$user = $stmt->fetch() ?: ['name' => 'Admin', 'email' => 'Not Set', 'profile_pic' => null, 'created_at' => 'now'];
$imagePath = !empty($user['profile_pic']) ? htmlspecialchars($user['profile_pic']) : '../assets/images/user/avatar-2.jpg';

// ── Filters ──
$from = $_GET['from'] ?? date('Y-m-d', strtotime('-30 days'));
$to   = $_GET['to'] ?? date('Y-m-d');
$selected_tenant_id = isset($_GET['tenant_id']) && $_GET['tenant_id'] !== '' ? (int)$_GET['tenant_id'] : null;

// ── All tenants for dropdown ──
$stmt = $pdo->prepare("SELECT id, name FROM tenants WHERE status != 'deleted' ORDER BY name");
$stmt->execute();
$all_tenants = $stmt->fetchAll();

// ── KPI: Active tenants (had activity in range) ──
$stmt = $pdo->prepare("
    SELECT COUNT(DISTINCT al.tenant_id) as active_tenants
    FROM activity_log al
    WHERE DATE(al.created_at) BETWEEN ? AND ?
");
$stmt->execute([$from, $to]);
$active_tenants = $stmt->fetch()['active_tenants'];

// ── KPI: Activities today ──
$stmt = $pdo->prepare("SELECT COUNT(*) as count FROM activity_log WHERE DATE(created_at) = CURDATE()");
$stmt->execute();
$activities_today = $stmt->fetch()['count'];

// ── KPI: Total activities in range ──
$stmt = $pdo->prepare("SELECT COUNT(*) as count FROM activity_log WHERE DATE(created_at) BETWEEN ? AND ?");
$stmt->execute([$from, $to]);
$activities_total = $stmt->fetch()['count'];

// ── KPI: Unique users active in range ──
$stmt = $pdo->prepare("SELECT COUNT(DISTINCT user_id) as count FROM activity_log WHERE user_id IS NOT NULL AND DATE(created_at) BETWEEN ? AND ?");
$stmt->execute([$from, $to]);
$active_users_range = $stmt->fetch()['count'];

// ── Daily activity trend ──
$stmt = $pdo->prepare("
    SELECT DATE(created_at) as day, COUNT(*) as count, COUNT(DISTINCT tenant_id) as tenants
    FROM activity_log
    WHERE DATE(created_at) BETWEEN ? AND ?
    GROUP BY DATE(created_at)
    ORDER BY day
");
$stmt->execute([$from, $to]);
$daily_map = [];
foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $r) {
    $daily_map[$r['day']] = ['count' => $r['count'], 'tenants' => $r['tenants']];
}
$daily_labels = [];
$daily_activities = [];
$daily_tenant_counts = [];
$begin = new DateTime($from);
$end   = new DateTime($to);
$end->modify('+1 day');
foreach (new DatePeriod($begin, new DateInterval('P1D'), $end) as $d) {
    $key = $d->format('Y-m-d');
    $daily_labels[] = $d->format('M d');
    $daily_activities[] = (int)($daily_map[$key]['count'] ?? 0);
    $daily_tenant_counts[] = (int)($daily_map[$key]['tenants'] ?? 0);
}

// ── Activity by hour (peak usage) ──
$stmt = $pdo->prepare("SELECT HOUR(created_at) as hr, COUNT(*) as count FROM activity_log WHERE DATE(created_at) BETWEEN ? AND ? GROUP BY HOUR(created_at) ORDER BY hr");
$stmt->execute([$from, $to]);
$hourly_raw = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);
$hourly_data = [];
for ($h = 0; $h < 24; $h++) {
    $hourly_data[] = (int)($hourly_raw[$h] ?? 0);
}

// ── Feature usage breakdown ──
$stmt = $pdo->prepare("SELECT table_name, COUNT(*) as count FROM activity_log WHERE DATE(created_at) BETWEEN ? AND ? GROUP BY table_name ORDER BY count DESC LIMIT 15");
$stmt->execute([$from, $to]);
$feature_usage = $stmt->fetchAll();
$total_feature = array_sum(array_column($feature_usage, 'count'));

// ── Action breakdown ──
$stmt = $pdo->prepare("SELECT action, COUNT(*) as count FROM activity_log WHERE DATE(created_at) BETWEEN ? AND ? GROUP BY action");
$stmt->execute([$from, $to]);
$action_breakdown = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);

// ── Inactive tenants (always visible) ──
$stmt = $pdo->prepare("
    SELECT t.id, t.name, DATEDIFF(NOW(), COALESCE(MAX(al.created_at), t.created_at)) as days_inactive
    FROM tenants t
    LEFT JOIN activity_log al ON t.id = al.tenant_id
    WHERE t.status != 'deleted'
    GROUP BY t.id, t.name
    HAVING days_inactive > 7
    ORDER BY days_inactive DESC
");
$stmt->execute();
$inactive_tenants = $stmt->fetchAll();

// ── Selected tenant details ──
$tenant_info = null;
$tenant_stats = null;
$tenant_activity_stream = [];
$tenant_login_history = [];
if ($selected_tenant_id) {
    $stmt = $pdo->prepare("SELECT id, name, status, created_at FROM tenants WHERE id = ? AND status != 'deleted'");
    $stmt->execute([$selected_tenant_id]);
    $tenant_info = $stmt->fetch();

    if ($tenant_info) {
        $stmt = $pdo->prepare("
            SELECT
                COUNT(DISTINCT al.id) as activities,
                COUNT(DISTINCT al.user_id) as active_users,
                MAX(al.created_at) as last_activity,
                COUNT(DISTINCT CASE WHEN al.action = 'create' THEN al.id END) as creates,
                COUNT(DISTINCT CASE WHEN al.action = 'update' THEN al.id END) as updates,
                COUNT(DISTINCT CASE WHEN al.action = 'delete' THEN al.id END) as deletes,
                COUNT(DISTINCT CASE WHEN al.table_name = 'chat_messages' THEN 1 END) as uses_chat,
                COUNT(DISTINCT CASE WHEN al.table_name IN ('ticket_bookings','ticket_refunds') THEN 1 END) as uses_tickets,
                COUNT(DISTINCT CASE WHEN al.table_name IN ('umrah_bookings','umrah_refunds') THEN 1 END) as uses_umrah,
                COUNT(DISTINCT CASE WHEN al.table_name IN ('hotel_bookings','hotel_refunds') THEN 1 END) as uses_hotels,
                COUNT(DISTINCT CASE WHEN al.table_name IN ('visa_applications','visa_refunds') THEN 1 END) as uses_visas,
                (SELECT COUNT(*) FROM login_history lh WHERE lh.tenant_id = ? AND lh.action = 'login' AND DATE(lh.action_time) BETWEEN ? AND ?) as logins,
                (SELECT COUNT(*) FROM users u WHERE u.tenant_id = ? AND u.deleted_at IS NULL) as total_users,
                (SELECT COUNT(*) FROM users u WHERE u.tenant_id = ? AND u.deleted_at IS NULL AND u.fired = 0) as active_employees
            FROM activity_log al
            WHERE al.tenant_id = ? AND DATE(al.created_at) BETWEEN ? AND ?
        ");
        $stmt->execute([$selected_tenant_id, $from, $to, $selected_tenant_id, $selected_tenant_id, $selected_tenant_id, $from, $to]);
        $tenant_stats = $stmt->fetch();

        $stmt = $pdo->prepare("
            SELECT al.*, u.name as user_name
            FROM activity_log al
            LEFT JOIN users u ON al.user_id = u.id
            WHERE al.tenant_id = ? AND DATE(al.created_at) BETWEEN ? AND ?
            ORDER BY al.created_at DESC LIMIT 50
        ");
        $stmt->execute([$selected_tenant_id, $from, $to]);
        $tenant_activity_stream = $stmt->fetchAll();

        $stmt = $pdo->prepare("
            SELECT lh.*, u.name as user_name
            FROM login_history lh
            LEFT JOIN users u ON lh.user_id = u.id
            WHERE lh.tenant_id = ? AND DATE(lh.action_time) BETWEEN ? AND ?
            ORDER BY lh.action_time DESC LIMIT 30
        ");
        $stmt->execute([$selected_tenant_id, $from, $to]);
        $tenant_login_history = $stmt->fetchAll();
    }
}
?>
<?php include '../includes/header_super_admin.php'; ?>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Sora:wght@300;400;500;600;700&family=JetBrains+Mono:wght@400;500&display=swap" rel="stylesheet">
<style>
:root {
  --bg: #f8fafc; --surface: #ffffff; --surface2: #f1f5f9; --border: #e5e7eb;
  --text: #1f2937; --muted: #6b7280; --accent: #4099ff; --accent2: #2ed8b6;
  --green: #10b981; --amber: #f59e0b; --red: #ef4444; --blue: #3b82f6;
  --radius: 14px;
}
*, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
html { font-size: 14px; }
body { font-family: 'Sora', sans-serif; background: var(--bg); color: var(--text); min-height: 100vh; }
.ta-wrap { display: flex; flex-direction: column; min-height: 100vh; }
.ta-btn {
  font-size: .75rem; font-weight: 600; font-family: 'Sora', sans-serif;
  padding: 6px 14px; border-radius: 20px; cursor: pointer; border: none;
  display: inline-flex; align-items: center; gap: 5px; text-decoration: none; transition: all .15s;
}
.ta-btn-ghost { background: var(--surface2); color: var(--muted); border: 1px solid var(--border); }
.ta-btn-ghost:hover { color: var(--text); }
.ta-btn-primary { background: linear-gradient(135deg, var(--accent), var(--accent2)); color: #fff; }
.ta-btn-primary:hover { opacity: .85; transform: translateY(-1px); }

.ta-kpi-rail {
  display: grid; grid-template-columns: repeat(4, 1fr);
  background: var(--surface); border-bottom: 1px solid var(--border);
}
.ta-kpi-item { padding: 16px 24px; border-right: 1px solid var(--border); position: relative; overflow: hidden; cursor: default; }
.ta-kpi-item:last-child { border-right: none; }
.ta-kpi-item::after {
  content: ''; position: absolute; bottom: 0; left: 0; right: 0; height: 2px;
  background: var(--accent); transform: scaleX(0); transition: transform .3s; transform-origin: left;
}
.ta-kpi-item:hover::after { transform: scaleX(1); }
.ta-kpi-val { font-size: 1.5rem; font-weight: 700; letter-spacing: -.03em; font-family: 'JetBrains Mono', monospace; line-height: 1; }
.ta-kpi-label { font-size: .7rem; color: var(--muted); margin-top: 4px; font-weight: 500; }
.ta-kpi-delta { font-size: .68rem; font-family: 'JetBrains Mono', monospace; margin-top: 5px; font-weight: 600; display: inline-flex; align-items: center; gap: 3px; }
.ta-kpi-delta.up { color: var(--green); }
.ta-kpi-delta.down { color: var(--red); }
.ta-kpi-delta.neu { color: var(--muted); }

.ta-content { padding: 24px 28px; display: flex; flex-direction: column; gap: 24px; }

.ta-shdr {
  display: flex; align-items: center; justify-content: space-between; margin-bottom: 14px;
}
.ta-shdr h2 {
  font-size: .72rem; text-transform: uppercase; letter-spacing: .1em;
  color: var(--muted); font-weight: 700;
}

.ta-card {
  background: var(--surface); border: 1px solid var(--border);
  border-radius: var(--radius); overflow: hidden; transition: border-color .2s, transform .2s;
}
.ta-card:hover { border-color: rgba(108,99,255,.25); transform: translateY(-1px); }
.ta-card-hdr { padding: 14px 20px; border-bottom: 1px solid var(--border); display: flex; align-items: center; justify-content: space-between; }
.ta-card-hdr h3 { font-size: .85rem; font-weight: 600; }
.ta-card-body { padding: 18px 20px; }

.ta-2col { display: grid; grid-template-columns: 1fr 1fr; gap: 14px; }

.pill {
  font-size: .62rem; font-weight: 700; padding: 3px 8px; border-radius: 20px;
  text-transform: uppercase; letter-spacing: .04em; white-space: nowrap;
}
.pill-green { background: rgba(34,211,160,.12); color: var(--green); }
.pill-red { background: rgba(244,63,94,.12); color: var(--red); }
.pill-amber { background: rgba(245,158,11,.12); color: var(--amber); }
.pill-blue { background: rgba(56,189,248,.12); color: var(--blue); }
.pill-purple { background: rgba(108,99,255,.12); color: var(--accent2); }
.pill-muted { background: var(--surface2); color: var(--muted); }

/* Filter bar */
.filter-bar {
  display: flex; align-items: center; gap: 12px; padding: 14px 20px;
  background: var(--surface); border: 1px solid var(--border); border-radius: var(--radius);
  flex-wrap: wrap;
}
.filter-bar label { font-size: .72rem; font-weight: 600; color: var(--muted); }
.filter-bar input[type="date"], .filter-bar select {
  background: var(--surface2); border: 1px solid var(--border); color: var(--text);
  border-radius: 8px; padding: 6px 12px; font-family: 'JetBrains Mono', monospace; font-size: .78rem;
}
.filter-bar select { font-family: 'Sora', sans-serif; min-width: 180px; }

/* Tenant detail stats */
.td-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(130px, 1fr)); gap: 10px; }
.td-item {
  background: var(--surface2); border-radius: 9px; padding: 14px 12px; text-align: center;
}
.td-val { font-size: 1.2rem; font-weight: 700; font-family: 'JetBrains Mono', monospace; }
.td-label { font-size: .62rem; color: var(--muted); margin-top: 3px; font-weight: 600; text-transform: uppercase; letter-spacing: .04em; }
.td-feat { display: flex; flex-wrap: wrap; gap: 4px; margin-top: 4px; justify-content: center; }
.td-feat span {
  font-size: .6rem; padding: 2px 7px; border-radius: 20px;
  background: rgba(34,211,160,.12); color: var(--green); font-weight: 700;
}
.td-feat span.off { background: var(--surface2); color: var(--muted); }

/* Stream */
.stream { display: flex; flex-direction: column; gap: 6px; max-height: 450px; overflow-y: auto; }
.stream-item {
  display: flex; align-items: center; gap: 10px; padding: 8px 12px;
  background: var(--surface2); border-radius: 8px; font-size: .75rem;
}
.stream-item:hover { background: rgba(108,99,255,.08); }
.stream-dot { width: 6px; height: 6px; border-radius: 50%; flex-shrink: 0; }
.stream-dot.create { background: var(--green); }
.stream-dot.update { background: var(--blue); }
.stream-dot.delete { background: var(--red); }
.stream-dot.other { background: var(--muted); }
.stream-info { flex: 1; min-width: 0; }
.stream-info strong { font-weight: 600; }
.stream-info .muted { color: var(--muted); font-size: .7rem; }
.stream-time { font-size: .65rem; color: var(--muted); font-family: 'JetBrains Mono', monospace; flex-shrink: 0; }

/* Inactive tenant cards */
.inactive-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(200px, 1fr)); gap: 10px; }
.inactive-card {
  background: var(--surface); border: 1px solid var(--border); border-radius: 10px;
  padding: 14px 16px; display: flex; align-items: center; gap: 10px;
}
.inactive-card .ic-name { font-weight: 600; font-size: .82rem; }
.inactive-card .ic-days { font-size: .68rem; color: var(--muted); }

body { background: var(--bg) !important; }
.pcoded-main-container, .pcoded-wrapper, .pcoded-content, .pcoded-inner-content { background: var(--bg) !important; }
.page-header { background: transparent !important; border: none !important; box-shadow: none !important; }
.page-header h5 { color: var(--text) !important; }
.breadcrumb { background: transparent !important; }
.breadcrumb-item a, .breadcrumb-item.active { color: var(--muted) !important; }
.ta-kpi-rail { position: sticky; top: 0; z-index: 30; }
</style>

<div class="pcoded-main-container">
  <div class="pcoded-wrapper">
    <div class="pcoded-content">
      <div class="pcoded-inner-content">

        <div class="page-header">
          <div class="page-block">
            <div class="row align-items-center">
              <div class="col-md-12">
                <div class="page-header-title">
                  <h5 class="m-b-10">Tenant Activity & Usage</h5>
                </div>
                <ul class="breadcrumb">
                  <li class="breadcrumb-item"><a href="dashboard.php"><i class="feather icon-home"></i></a></li>
                  <li class="breadcrumb-item active">Tenant Activity</li>
                </ul>
              </div>
            </div>
          </div>
        </div>

        <div class="ta-wrap">

          <!-- ── FILTER BAR ── -->
          <div class="filter-bar">
            <label>Tenant:</label>
            <select name="tenant_id" onchange="applyFilter()">
              <option value="">— All Tenants (Overview) —</option>
              <?php foreach ($all_tenants as $t): ?>
              <option value="<?= $t['id'] ?>" <?= $selected_tenant_id === (int)$t['id'] ? 'selected' : '' ?>><?= htmlspecialchars($t['name']) ?></option>
              <?php endforeach; ?>
            </select>
            <label>From:</label>
            <input type="date" name="from" value="<?= htmlspecialchars($from) ?>" onchange="applyFilter()">
            <label>To:</label>
            <input type="date" name="to" value="<?= htmlspecialchars($to) ?>" onchange="applyFilter()">
            <a href="tenant_activity.php" class="ta-btn ta-btn-ghost" style="font-size:.7rem">Reset</a>
          </div>

          <!-- ── KPI RAIL (always global) ── -->
          <div class="ta-kpi-rail">
            <div class="ta-kpi-item">
              <div class="ta-kpi-val" style="color:var(--green)"><?= $active_tenants ?></div>
              <div class="ta-kpi-label">Active Tenants</div>
              <div class="ta-kpi-delta neu">with activity in selected range</div>
            </div>
            <div class="ta-kpi-item">
              <div class="ta-kpi-val" style="color:var(--blue)"><?= $activities_today ?></div>
              <div class="ta-kpi-label">Activities Today</div>
              <div class="ta-kpi-delta neu">system-wide</div>
            </div>
            <div class="ta-kpi-item">
              <div class="ta-kpi-val"><?= number_format($activities_total) ?></div>
              <div class="ta-kpi-label">Activities (Range)</div>
              <div class="ta-kpi-delta neu"><?= htmlspecialchars($from) ?> → <?= htmlspecialchars($to) ?></div>
            </div>
            <div class="ta-kpi-item">
              <div class="ta-kpi-val"><?= $active_users_range ?></div>
              <div class="ta-kpi-label">Active Users</div>
              <div class="ta-kpi-delta neu">unique users with actions</div>
            </div>
          </div>

          <!-- ── GLOBAL CHARTS ── -->
          <div class="ta-2col">
            <div class="ta-card">
              <div class="ta-card-hdr">
                <h3>Daily Activity Trend</h3>
                <span class="pill pill-blue"><?= count($daily_activities) ?> days</span>
              </div>
              <div class="ta-card-body">
                <canvas id="dailyChart" height="200"></canvas>
              </div>
            </div>
            <div class="ta-card">
              <div class="ta-card-hdr">
                <h3>Activity by Hour (Peak Usage)</h3>
                <span class="pill pill-purple">24h</span>
              </div>
              <div class="ta-card-body">
                <canvas id="hourlyChart" height="200"></canvas>
              </div>
            </div>
          </div>

          <div class="ta-2col">
            <div class="ta-card">
              <div class="ta-card-hdr">
                <h3>Feature Usage (Top 15 Tables)</h3>
                <span class="pill pill-green"><?= number_format($total_feature) ?> total</span>
              </div>
              <div class="ta-card-body">
                <?php if (!empty($feature_usage)): ?>
                <div style="display:flex;flex-direction:column;gap:8px">
                  <?php $max_fu = max(array_column($feature_usage, 'count')); ?>
                  <?php foreach ($feature_usage as $fu):
                    $pct = $max_fu > 0 ? round($fu['count'] / $max_fu * 100) : 0;
                  ?>
                  <div>
                    <div style="display:flex;justify-content:space-between;font-size:.75rem;margin-bottom:3px">
                      <span style="font-weight:500"><?= htmlspecialchars($fu['table_name']) ?></span>
                      <span style="font-family:'JetBrains Mono',monospace;color:var(--muted)"><?= number_format($fu['count']) ?></span>
                    </div>
                    <div style="height:5px;background:var(--surface2);border-radius:10px;overflow:hidden">
                      <div style="height:100%;width:<?= $pct ?>%;background:linear-gradient(90deg,var(--accent),var(--accent2));border-radius:10px"></div>
                    </div>
                  </div>
                  <?php endforeach; ?>
                </div>
                <?php else: ?>
                <p style="text-align:center;color:var(--muted);padding:20px 0">No activity data</p>
                <?php endif; ?>
              </div>
            </div>
            <div class="ta-card">
              <div class="ta-card-hdr">
                <h3>Action Breakdown</h3>
              </div>
              <div class="ta-card-body">
                <canvas id="actionChart" height="200"></canvas>
              </div>
            </div>
          </div>

          <!-- ── SELECTED TENANT DETAIL ── -->
          <?php if ($selected_tenant_id && $tenant_info): ?>
          <div class="ta-card" style="border-color:var(--accent)">
            <div class="ta-card-hdr" style="background:linear-gradient(135deg,var(--accent),var(--accent2));color:#fff">
              <h3 style="color:#fff;font-weight:600"><?= htmlspecialchars($tenant_info['name']) ?></h3>
              <span class="pill <?= $tenant_info['status'] === 'active' ? 'pill-green' : 'pill-muted' ?>" style="background:rgba(255,255,255,.2);color:#fff">
                <?= htmlspecialchars($tenant_info['status']) ?>
              </span>
            </div>
            <div class="ta-card-body">
              <?php if ($tenant_stats): ?>
              <div class="td-grid">
                <div class="td-item">
                  <div class="td-val" style="color:var(--green)"><?= number_format($tenant_stats['activities']) ?></div>
                  <div class="td-label">Activities</div>
                </div>
                <div class="td-item">
                  <div class="td-val" style="color:var(--green)">+<?= number_format($tenant_stats['creates']) ?></div>
                  <div class="td-label">Creates</div>
                </div>
                <div class="td-item">
                  <div class="td-val" style="color:var(--blue)">~<?= number_format($tenant_stats['updates']) ?></div>
                  <div class="td-label">Updates</div>
                </div>
                <div class="td-item">
                  <div class="td-val" style="color:var(--red)">-<?= number_format($tenant_stats['deletes']) ?></div>
                  <div class="td-label">Deletes</div>
                </div>
                <div class="td-item">
                  <div class="td-val"><?= $tenant_stats['logins'] ?></div>
                  <div class="td-label">Logins (Range)</div>
                </div>
                <div class="td-item">
                  <div class="td-val"><?= $tenant_stats['active_users'] ?></div>
                  <div class="td-label">Active Users</div>
                </div>
                <div class="td-item">
                  <div class="td-val"><?= $tenant_stats['active_employees'] ?></div>
                  <div class="td-label">Employees</div>
                </div>
                <div class="td-item" style="grid-column:span 2">
                  <div class="td-label" style="margin-bottom:6px">Features Used</div>
                  <div class="td-feat">
                    <span class="<?= $tenant_stats['uses_chat'] ? '' : 'off' ?>">Chat</span>
                    <span class="<?= $tenant_stats['uses_tickets'] ? '' : 'off' ?>">Tickets</span>
                    <span class="<?= $tenant_stats['uses_umrah'] ? '' : 'off' ?>">Umrah</span>
                    <span class="<?= $tenant_stats['uses_hotels'] ? '' : 'off' ?>">Hotels</span>
                    <span class="<?= $tenant_stats['uses_visas'] ? '' : 'off' ?>">Visas</span>
                  </div>
                </div>
              </div>

              <div style="height:16px"></div>

              <div class="ta-2col">
                <div>
                  <div class="ta-shdr"><h2>Activity Stream</h2></div>
                  <?php if (!empty($tenant_activity_stream)): ?>
                  <div class="stream" style="max-height:350px">
                    <?php foreach ($tenant_activity_stream as $as):
                      $dotCls = match($as['action']) { 'create' => 'create', 'update' => 'update', 'delete' => 'delete', default => 'other' };
                      $userName = $as['user_name'] ?? 'System';
                    ?>
                    <div class="stream-item">
                      <div class="stream-dot <?= $dotCls ?>"></div>
                      <div class="stream-info">
                        <strong><?= htmlspecialchars(ucfirst($as['action'])) ?></strong>
                        on <strong><?= htmlspecialchars($as['table_name']) ?></strong>
                        <span class="muted">by <?= htmlspecialchars($userName) ?></span>
                      </div>
                      <div class="stream-time"><?= date('M d, H:i', strtotime($as['created_at'])) ?></div>
                    </div>
                    <?php endforeach; ?>
                  </div>
                  <?php else: ?><p style="color:var(--muted);padding:12px 0;font-size:.78rem">No activity for this period</p><?php endif; ?>
                </div>
                <div>
                  <div class="ta-shdr"><h2>Login History</h2></div>
                  <?php if (!empty($tenant_login_history)): ?>
                  <div class="stream" style="max-height:350px">
                    <?php foreach ($tenant_login_history as $lh):
                      $dotCls = $lh['action'] === 'login' ? 'create' : 'delete';
                    ?>
                    <div class="stream-item">
                      <div class="stream-dot <?= $dotCls ?>"></div>
                      <div class="stream-info">
                        <strong><?= htmlspecialchars(ucfirst($lh['action'])) ?></strong>
                        <span class="muted"><?= htmlspecialchars($lh['user_name'] ?? 'Unknown') ?></span>
                      </div>
                      <div class="stream-time"><?= date('M d, H:i', strtotime($lh['action_time'])) ?></div>
                    </div>
                    <?php endforeach; ?>
                  </div>
                  <?php else: ?><p style="color:var(--muted);padding:12px 0;font-size:.78rem">No login history for this period</p><?php endif; ?>
                </div>
              </div>
              <?php else: ?>
              <p style="text-align:center;color:var(--muted);padding:24px 0">No activity data for this tenant in the selected range</p>
              <?php endif; ?>
            </div>
          </div>
          <?php elseif ($selected_tenant_id && !$tenant_info): ?>
          <div class="ta-card">
            <div class="ta-card-body" style="text-align:center;padding:24px;color:var(--red)">
              Tenant not found or has been deleted.
            </div>
          </div>
          <?php endif; ?>

          <!-- ── INACTIVE TENANTS ALERT ── -->
          <?php if (!empty($inactive_tenants)): ?>
          <div class="ta-card">
            <div class="ta-card-hdr">
              <h3>⚠ Inactive Tenants (No activity for 7+ days)</h3>
              <span class="pill pill-red"><?= count($inactive_tenants) ?> tenants</span>
            </div>
            <div class="ta-card-body">
              <div class="inactive-grid">
                <?php foreach ($inactive_tenants as $it):
                  $days = $it['days_inactive'];
                  $color = $days > 30 ? 'var(--red)' : ($days > 14 ? 'var(--amber)' : 'var(--muted)');
                ?>
                <div class="inactive-card">
                  <div style="flex:1;min-width:0">
                    <div class="ic-name"><?= htmlspecialchars($it['name']) ?></div>
                    <div class="ic-days" style="color:<?= $color ?>"><?= $days ?> days inactive</div>
                  </div>
                  <a href="tenant_activity.php?tenant_id=<?= $it['id'] ?>&from=<?= htmlspecialchars($from) ?>&to=<?= htmlspecialchars($to) ?>" class="ta-btn ta-btn-ghost" style="padding:4px 10px;font-size:.65rem">Inspect</a>
                </div>
                <?php endforeach; ?>
              </div>
            </div>
          </div>
          <?php endif; ?>

        </div><!-- /ta-wrap -->

        <!-- Profile Modal -->
        <div class="modal fade" id="profileModal" tabindex="-1" role="dialog">
          <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content" style="background:var(--surface);color:var(--text);border:1px solid var(--border);border-radius:var(--radius)">
              <div class="modal-header" style="background:linear-gradient(135deg,var(--accent),var(--accent2));border-radius:var(--radius) var(--radius) 0 0;border-bottom:none">
                <h5 class="modal-title" style="color:#fff;font-weight:600">User Profile</h5>
                <button type="button" class="close" data-dismiss="modal" style="color:rgba(255,255,255,.7)"><span>&times;</span></button>
              </div>
              <div class="modal-body" style="text-align:center;padding:20px">
                <img src="<?= $imagePath ?>" style="width:80px;height:80px;border-radius:50%;object-fit:cover;border:3px solid var(--accent);margin-bottom:12px" alt="Profile">
                <div style="font-weight:600;font-size:1rem"><?= htmlspecialchars($user['name']) ?></div>
                <div style="margin:4px 0"><span class="pill pill-purple"><?= htmlspecialchars($_SESSION['role']) ?></span></div>
                <div style="color:var(--muted);font-size:.78rem;margin-top:8px"><?= htmlspecialchars($user['email']) ?></div>
                <div style="color:var(--muted);font-size:.72rem;margin-top:4px">Joined <?= date('M d, Y', strtotime($user['created_at'])) ?></div>
              </div>
              <div class="modal-footer" style="border-top:1px solid var(--border);padding:14px 20px">
                <button type="button" class="ta-btn ta-btn-ghost" data-dismiss="modal">Close</button>
              </div>
            </div>
          </div>
        </div>

      </div>
    </div>
  </div>
</div>

<script src="../assets/js/vendor-all.min.js"></script>
<script src="../assets/plugins/bootstrap/js/bootstrap.min.js"></script>
<script src="../assets/js/pcoded.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/Chart.js/3.9.1/chart.min.js"></script>
<script>
function applyFilter() {
  const tenant = document.querySelector('select[name="tenant_id"]').value;
  const from = document.querySelector('input[name="from"]').value;
  const to = document.querySelector('input[name="to"]').value;
  let url = 'tenant_activity.php?';
  if (tenant) url += 'tenant_id=' + tenant + '&';
  if (from) url += 'from=' + from + '&';
  if (to) url += 'to=' + to;
  window.location.href = url;
}

Chart.defaults.color = '#6b7280';
Chart.defaults.borderColor = 'rgba(255,255,255,0.05)';
Chart.defaults.font.family = "'JetBrains Mono', monospace";
Chart.defaults.font.size = 11;

new Chart(document.getElementById('dailyChart'), {
  type: 'bar',
  data: {
    labels: <?= json_encode(array_values($daily_labels)) ?>,
    datasets: [{
      label: 'Activities', data: <?= json_encode($daily_activities) ?>,
      backgroundColor: 'rgba(64,153,255,0.6)', borderColor: '#4099ff',
      borderWidth: 1, borderRadius: 3
    }]
  },
  options: {
    responsive: true, maintainAspectRatio: false,
    plugins: { legend: { display: false } },
    scales: {
      y: { beginAtZero: true, grid: { color: 'rgba(255,255,255,0.04)' }, ticks: { stepSize: 1 } },
      x: { grid: { display: false }, ticks: { maxTicksLimit: 15 } }
    }
  }
});

new Chart(document.getElementById('hourlyChart'), {
  type: 'bar',
  data: {
    labels: ['00','01','02','03','04','05','06','07','08','09','10','11','12','13','14','15','16','17','18','19','20','21','22','23'],
    datasets: [{
      label: 'Activities', data: <?= json_encode($hourly_data) ?>,
      backgroundColor: [<?php foreach (range(0, 23) as $h): ?>'<?= $h >= 8 && $h <= 18 ? 'rgba(46,216,182,0.7)' : 'rgba(107,114,128,0.4)' ?>',<?php endforeach; ?>],
      borderColor: [<?php foreach (range(0, 23) as $h): ?>'<?= $h >= 8 && $h <= 18 ? '#2ed8b6' : '#6b7280' ?>',<?php endforeach; ?>],
      borderWidth: 1, borderRadius: 3
    }]
  },
  options: {
    responsive: true, maintainAspectRatio: false,
    plugins: { legend: { display: false } },
    scales: {
      y: { beginAtZero: true, grid: { color: 'rgba(255,255,255,0.04)' }, ticks: { stepSize: 1 } },
      x: { grid: { display: false } }
    }
  }
});

new Chart(document.getElementById('actionChart'), {
  type: 'doughnut',
  data: {
    labels: <?= json_encode(array_keys($action_breakdown)) ?>,
    datasets: [{
      data: <?= json_encode(array_values($action_breakdown)) ?>,
      backgroundColor: ['#10b981', '#3b82f6', '#ef4444', '#f59e0b', '#6b7280'],
      borderColor: '#ffffff', borderWidth: 3, hoverOffset: 6
    }]
  },
  options: {
    responsive: true, maintainAspectRatio: false, cutout: '60%',
    plugins: { legend: { position: 'right', labels: { usePointStyle: true, pointStyle: 'circle', padding: 14 } } }
  }
});
</script>
<?php include '../includes/footer.php'; ?>
