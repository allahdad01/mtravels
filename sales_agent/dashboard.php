<?php
session_start();
require_once '../includes/db.php';

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

// Generate CSRF token if not exists
if (!isset($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// Check if user is a sales agent
if (!isset($_SESSION['user_id']) || !isset($_SESSION['role']) || $_SESSION['role'] !== 'sales_agent' || !is_null($_SESSION['tenant_id'])) {
    header('Location: ../login.php');
    exit();
}

// Get sales agent info
$stmt = $pdo->prepare("SELECT sa.id, sa.name, sa.email, sa.phone, sa.province, sa.region,
                              sa.commission_rate, sa.salary_type, sa.base_salary, sa.status
                       FROM sales_agents sa
                       WHERE sa.user_id = ?");
$stmt->execute([$_SESSION['user_id']]);
$agent = $stmt->fetch();

if (!$agent) {
    header('Location: ../login.php?error=agent_not_found');
    exit();
}

$agent_id = $agent['id'];

$stmt = $pdo->prepare("SELECT COUNT(*) as count FROM sales_agent_tenants WHERE sales_agent_id = ? AND status = 'active'");
$stmt->execute([$agent_id]);
$tenant_count = $stmt->fetch()['count'];

$stmt = $pdo->prepare("SELECT COALESCE(SUM(commission_amount), 0) as total FROM sales_agent_commissions WHERE sales_agent_id = ? AND status IN ('approved', 'paid')");
$stmt->execute([$agent_id]);
$total_earned = $stmt->fetch()['total'];

$stmt = $pdo->prepare("SELECT COALESCE(SUM(commission_amount), 0) as total FROM sales_agent_commissions WHERE sales_agent_id = ? AND status = 'pending'");
$stmt->execute([$agent_id]);
$pending_commission = $stmt->fetch()['total'];

$current_month = date('Y-m-01');
$stmt = $pdo->prepare("SELECT COALESCE(SUM(commission_amount), 0) as total FROM sales_agent_commissions WHERE sales_agent_id = ? AND period_month = ?");
$stmt->execute([$agent_id, $current_month]);
$this_month_commission = $stmt->fetch()['total'];

$stmt = $pdo->prepare("SELECT COALESCE(SUM(commission_amount), 0) as total FROM sales_agent_commissions WHERE sales_agent_id = ? AND YEAR(period_month) = YEAR(NOW()) AND status IN ('approved', 'paid')");
$stmt->execute([$agent_id]);
$yearly_earned = $stmt->fetch()['total'];

$stmt = $pdo->prepare("SELECT COALESCE(AVG(commission_amount), 0) as avg FROM sales_agent_commissions WHERE sales_agent_id = ? AND status IN ('approved', 'paid')");
$stmt->execute([$agent_id]);
$avg_monthly = $stmt->fetch()['avg'];

$stmt = $pdo->prepare("SELECT COUNT(*) as count FROM sales_agent_commissions WHERE sales_agent_id = ?");
$stmt->execute([$agent_id]);
$commission_records = $stmt->fetch()['count'];

$stmt = $pdo->prepare("SELECT period_month, commission_amount, status FROM sales_agent_commissions WHERE sales_agent_id = ? ORDER BY period_month DESC LIMIT 5");
$stmt->execute([$agent_id]);
$recent_commissions = $stmt->fetchAll();

$stmt = $pdo->prepare("SELECT t.id, t.name, sat.subscription_start_date, sat.commission_earned, sat.status FROM sales_agent_tenants sat JOIN tenants t ON sat.tenant_id = t.id WHERE sat.sales_agent_id = ? ORDER BY sat.created_at DESC LIMIT 6");
$stmt->execute([$agent_id]);
$recent_tenants = $stmt->fetchAll();

// Greeting
$hour = (int)date('H');
if ($hour < 12)      $greeting = "Good morning";
elseif ($hour < 18)  $greeting = "Good afternoon";
else                 $greeting = "Good evening";
?>
<?php include 'includes/header_sales_agent.php'; ?>

<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@300;400;500;600;700&family=Sora:wght@400;600;700&display=swap" rel="stylesheet">

<style>
/* ─────────────────────────────────────────
   CSS VARIABLES
───────────────────────────────────────── */
:root {
  --teal:       #2ed8b6;
  --blue:       #4099ff;
  --grad:       linear-gradient(135deg, #4099ff 0%, #2ed8b6 100%);
  --grad-rev:   linear-gradient(135deg, #2ed8b6 0%, #4099ff 100%);
  --grad-soft:  linear-gradient(135deg, rgba(64,153,255,0.08) 0%, rgba(46,216,182,0.08) 100%);

  --bg:         #F0F4F8;
  --bg-2:       #E8EDF3;
  --surface:    #FFFFFF;
  --surface-2:  #F7F9FC;

  --teal-dim:   rgba(46,216,182,0.12);
  --teal-dim2:  rgba(46,216,182,0.06);
  --blue-dim:   rgba(64,153,255,0.12);
  --blue-dim2:  rgba(64,153,255,0.06);

  --border:     rgba(0,0,0,0.07);
  --border-2:   rgba(0,0,0,0.04);

  --text-1:     #0F172A;
  --text-2:     #475569;
  --text-3:     #94A3B8;

  --green:      #10B981;
  --green-dim:  rgba(16,185,129,0.10);
  --amber:      #F59E0B;
  --amber-dim:  rgba(245,158,11,0.10);
  --red:        #EF4444;

  --radius:     14px;
  --radius-sm:  9px;
  --shadow-sm:  0 1px 3px rgba(0,0,0,0.06), 0 1px 2px rgba(0,0,0,0.04);
  --shadow:     0 4px 16px rgba(0,0,0,0.07), 0 1px 3px rgba(0,0,0,0.04);
  --shadow-md:  0 8px 30px rgba(64,153,255,0.10), 0 2px 8px rgba(0,0,0,0.05);
}

/* ─────────────────────────────────────────
   LAYOUT WRAPPER
───────────────────────────────────────── */
.sa-dash {
  background: var(--bg);
  padding: 28px 24px 48px;
  font-family: 'Plus Jakarta Sans', sans-serif;
  color: var(--text-1);
  min-height: 100vh;
}

/* ─────────────────────────────────────────
   UTILITIES
───────────────────────────────────────── */
.grad-text {
  background: var(--grad);
  -webkit-background-clip: text;
  -webkit-text-fill-color: transparent;
  background-clip: text;
}
.sec-label {
  font-size: 10px;
  font-weight: 700;
  letter-spacing: 0.15em;
  text-transform: uppercase;
  color: var(--text-3);
  margin-bottom: 14px;
}

/* ─────────────────────────────────────────
   WELCOME BANNER
───────────────────────────────────────── */
.welcome-banner {
  background: var(--grad);
  border-radius: var(--radius);
  padding: 28px 32px;
  display: flex;
  align-items: center;
  justify-content: space-between;
  margin-bottom: 28px;
  position: relative;
  overflow: hidden;
  box-shadow: var(--shadow-md);
}
.welcome-banner::before {
  content: '';
  position: absolute; top: -50px; right: -30px;
  width: 220px; height: 220px;
  background: rgba(255,255,255,0.10);
  border-radius: 50%;
}
.welcome-banner::after {
  content: '';
  position: absolute; bottom: -70px; right: 120px;
  width: 170px; height: 170px;
  background: rgba(255,255,255,0.06);
  border-radius: 50%;
}
.wb-left { position: relative; z-index: 1; }
.wb-eyebrow {
  font-size: 10px;
  font-weight: 700;
  letter-spacing: 0.14em;
  text-transform: uppercase;
  color: rgba(255,255,255,0.70);
  margin-bottom: 5px;
}
.wb-name {
  font-family: 'Sora', sans-serif;
  font-size: 24px;
  font-weight: 700;
  color: #fff;
  margin-bottom: 10px;
  line-height: 1.2;
}
.wb-meta { display: flex; align-items: center; gap: 8px; flex-wrap: wrap; }
.wb-chip {
  display: inline-flex;
  align-items: center;
  gap: 5px;
  font-size: 11px;
  font-weight: 600;
  color: rgba(255,255,255,0.85);
  background: rgba(255,255,255,0.18);
  border-radius: 20px;
  padding: 4px 12px;
  backdrop-filter: blur(4px);
}
.wb-chip .dot { width: 6px; height: 6px; border-radius: 50%; background: #fff; flex-shrink: 0; }

.btn-new-tenant {
  position: relative; z-index: 1;
  display: inline-flex;
  align-items: center;
  gap: 8px;
  background: #fff;
  color: var(--blue);
  font-family: 'Plus Jakarta Sans', sans-serif;
  font-size: 12px;
  font-weight: 700;
  border: none;
  border-radius: var(--radius-sm);
  padding: 11px 22px;
  text-decoration: none;
  white-space: nowrap;
  box-shadow: 0 4px 14px rgba(0,0,0,0.15);
  transition: transform 0.15s, box-shadow 0.15s;
}
.btn-new-tenant:hover {
  transform: translateY(-2px);
  box-shadow: 0 8px 20px rgba(0,0,0,0.18);
  color: var(--blue);
  text-decoration: none;
}
.btn-new-tenant svg { width: 13px; height: 13px; }

/* ─────────────────────────────────────────
   METRIC CARDS
───────────────────────────────────────── */
.metric-grid {
  display: grid;
  grid-template-columns: repeat(4, 1fr);
  gap: 14px;
  margin-bottom: 32px;
}
@media (max-width: 992px) { .metric-grid { grid-template-columns: repeat(2,1fr); } }
@media (max-width: 576px)  { .metric-grid { grid-template-columns: 1fr; } }

.mcard {
  background: var(--surface);
  border: 1px solid var(--border);
  border-radius: var(--radius);
  padding: 22px 20px 18px;
  box-shadow: var(--shadow-sm);
  position: relative;
  overflow: hidden;
  transition: transform 0.18s, box-shadow 0.18s, border-color 0.18s;
}
.mcard:hover {
  transform: translateY(-3px);
  box-shadow: var(--shadow-md);
  border-color: rgba(64,153,255,0.20);
}
.mcard::before {
  content: '';
  position: absolute;
  top: 0; left: 0; right: 0;
  height: 3px;
  background: var(--grad);
  opacity: 0;
  transition: opacity 0.2s;
}
.mcard:hover::before { opacity: 1; }

.mcard-top {
  display: flex;
  align-items: flex-start;
  justify-content: space-between;
  margin-bottom: 16px;
}
.mcard-icon {
  width: 38px; height: 38px;
  border-radius: 10px;
  display: flex; align-items: center; justify-content: center;
}
.mcard-icon svg { width: 18px; height: 18px; }
.mcard-badge {
  font-size: 9px; font-weight: 700;
  letter-spacing: 0.06em; text-transform: uppercase;
  padding: 3px 9px; border-radius: 20px;
}
.mcard-value {
  font-family: 'Sora', sans-serif;
  font-size: 26px; font-weight: 700;
  color: var(--text-1);
  letter-spacing: -0.03em;
  line-height: 1;
  margin-bottom: 5px;
}
.mcard-label { font-size: 12px; font-weight: 600; color: var(--text-2); margin-bottom: 2px; }
.mcard-sub   { font-size: 10px; color: var(--text-3); }

/* ─────────────────────────────────────────
   PANELS (cards with header)
───────────────────────────────────────── */
.panel {
  background: var(--surface);
  border: 1px solid var(--border);
  border-radius: var(--radius);
  box-shadow: var(--shadow-sm);
  overflow: hidden;
  transition: box-shadow 0.18s, border-color 0.18s;
}
.panel:hover { box-shadow: var(--shadow); border-color: rgba(64,153,255,0.15); }

.panel-head {
  display: flex;
  align-items: center;
  justify-content: space-between;
  padding: 16px 20px 13px;
  border-bottom: 1px solid var(--border-2);
  background: var(--surface-2);
}
.panel-title {
  font-size: 13px; font-weight: 700;
  color: var(--text-1);
}
.panel-link {
  font-size: 11px; font-weight: 600;
  color: var(--blue);
  text-decoration: none;
  display: inline-flex; align-items: center; gap: 3px;
  opacity: 0.85; transition: opacity 0.15s;
}
.panel-link:hover { opacity: 1; text-decoration: none; }
.panel-body { padding: 4px 0; }
.panel-footer {
  padding: 12px 20px;
  border-top: 1px solid var(--border-2);
  background: var(--surface-2);
  text-align: center;
}

/* ─────────────────────────────────────────
   FIELD ROWS (profile/comp panels)
───────────────────────────────────────── */
.field-row {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: 10px;
  padding: 14px 20px;
}
.field-row + .field-row { border-top: 1px solid var(--border-2); }
.field-row.full { grid-template-columns: 1fr; }
.field-label {
  font-size: 9px; font-weight: 700;
  letter-spacing: 0.12em; text-transform: uppercase;
  color: var(--text-3); margin-bottom: 4px;
}
.field-value { font-size: 12px; font-weight: 600; color: var(--text-1); }

/* status badges */
.badge-active {
  display: inline-flex; align-items: center; gap: 5px;
  font-size: 10px; font-weight: 700;
  color: var(--green); background: var(--green-dim);
  border-radius: 20px; padding: 3px 10px;
}
.badge-active .dot { width: 5px; height: 5px; border-radius: 50%; background: var(--green); }
.badge-inactive {
  display: inline-flex; align-items: center; gap: 5px;
  font-size: 10px; font-weight: 700;
  color: var(--text-3); background: var(--bg);
  border-radius: 20px; padding: 3px 10px;
}
.edit-link {
  font-size: 10px; font-weight: 600;
  color: var(--blue); text-decoration: none;
  display: inline-flex; align-items: center; gap: 4px;
  opacity: 0.85; transition: opacity 0.15s;
}
.edit-link:hover { opacity: 1; }

/* ─────────────────────────────────────────
   INFO GRID (profile + comp side by side)
───────────────────────────────────────── */
.info-grid {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: 14px;
  margin-bottom: 32px;
}
@media (max-width: 768px) { .info-grid { grid-template-columns: 1fr; } }

/* ─────────────────────────────────────────
   STATS TILES
───────────────────────────────────────── */
.stats-grid {
  display: grid;
  grid-template-columns: repeat(3, 1fr);
  gap: 14px;
  margin-bottom: 32px;
}
@media (max-width: 768px) { .stats-grid { grid-template-columns: 1fr; } }

.stat-tile {
  background: var(--surface);
  border: 1px solid var(--border);
  border-radius: var(--radius);
  padding: 22px 20px;
  box-shadow: var(--shadow-sm);
  text-align: center;
  position: relative;
  overflow: hidden;
  transition: transform 0.18s, box-shadow 0.18s;
}
.stat-tile:hover { transform: translateY(-2px); box-shadow: var(--shadow); }
.stat-tile::after {
  content: '';
  position: absolute;
  bottom: 0; left: 15%; right: 15%;
  height: 2px;
  background: var(--grad);
  border-radius: 2px;
  opacity: 0.5;
}
.stat-icon {
  width: 42px; height: 42px;
  border-radius: 50%;
  display: flex; align-items: center; justify-content: center;
  margin: 0 auto 12px;
  background: var(--grad-soft);
}
.stat-icon svg { width: 20px; height: 20px; }
.stat-value {
  font-family: 'Sora', sans-serif;
  font-size: 22px; font-weight: 700;
  letter-spacing: -0.02em;
  margin-bottom: 4px;
}
.stat-label { font-size: 11px; font-weight: 600; color: var(--text-2); }

/* ─────────────────────────────────────────
   TWO-COLUMN ACTIVITY
───────────────────────────────────────── */
.two-col-grid {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: 14px;
  margin-bottom: 32px;
}
@media (max-width: 768px) { .two-col-grid { grid-template-columns: 1fr; } }

/* ─────────────────────────────────────────
   LIST ROWS
───────────────────────────────────────── */
.list-row {
  display: flex;
  align-items: center;
  gap: 12px;
  padding: 12px 20px;
  transition: background 0.12s;
}
.list-row:hover { background: var(--surface-2); }
.list-row + .list-row { border-top: 1px solid var(--border-2); }

.row-icon {
  width: 36px; height: 36px;
  border-radius: 9px;
  display: flex; align-items: center; justify-content: center;
  font-size: 10px; font-weight: 800;
  letter-spacing: 0.02em;
  flex-shrink: 0;
}
.row-body { flex: 1; min-width: 0; }
.row-title {
  font-size: 12px; font-weight: 600;
  color: var(--text-1);
  white-space: nowrap; overflow: hidden; text-overflow: ellipsis;
}
.row-sub { font-size: 10px; color: var(--text-3); margin-top: 1px; }
.row-right { text-align: right; flex-shrink: 0; }
.row-amount { font-size: 13px; font-weight: 700; color: var(--text-1); font-variant-numeric: tabular-nums; }

/* status pills */
.pill {
  display: inline-block;
  font-size: 9px; font-weight: 800;
  letter-spacing: 0.07em; text-transform: uppercase;
  padding: 2px 8px; border-radius: 20px; margin-top: 3px;
}
.pill-paid     { background: var(--teal-dim);  color: #0d9373; }
.pill-approved { background: var(--blue-dim);  color: #1d6fd8; }
.pill-pending  { background: var(--amber-dim); color: #b45309; }
.pill-active   { background: var(--teal-dim);  color: #0d9373; }
.pill-inactive { background: var(--bg-2);      color: var(--text-3); }

/* tenant avatar */
.tenant-avatar {
  width: 36px; height: 36px;
  border-radius: 9px;
  background: var(--grad-soft);
  border: 1px solid rgba(64,153,255,0.15);
  display: flex; align-items: center; justify-content: center;
  font-size: 11px; font-weight: 800;
  color: var(--blue);
  flex-shrink: 0;
}

/* ─────────────────────────────────────────
   SALARY CARD
───────────────────────────────────────── */
.salary-card {
  background: var(--surface);
  border: 1px solid var(--border);
  border-radius: var(--radius);
  padding: 24px 24px;
  box-shadow: var(--shadow-sm);
  margin-bottom: 32px;
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 20px;
}
.salary-label {
  font-size: 10px; font-weight: 700;
  letter-spacing: 0.12em; text-transform: uppercase;
  color: var(--text-3); margin-bottom: 6px;
}
.salary-value {
  font-family: 'Sora', sans-serif;
  font-size: 28px; font-weight: 700;
  letter-spacing: -0.03em;
}
.btn-outline {
  display: inline-flex; align-items: center; gap: 6px;
  font-size: 12px; font-weight: 700;
  color: var(--blue);
  background: var(--blue-dim);
  border: 1px solid rgba(64,153,255,0.20);
  border-radius: var(--radius-sm);
  padding: 10px 18px;
  text-decoration: none;
  transition: background 0.15s, border-color 0.15s;
}
.btn-outline:hover { background: rgba(64,153,255,0.18); border-color: rgba(64,153,255,0.35); text-decoration: none; color: var(--blue); }
</style>

<!-- [ Main Content ] start -->
<div class="pcoded-main-container">
  <div class="pcoded-wrapper">
    <div class="pcoded-content">
      <div class="pcoded-inner-content">

        <div class="main-body">
          <div class="page-wrapper sa-dash">

            <!-- ── WELCOME BANNER ── -->
            <div class="welcome-banner">
              <div class="wb-left">
                <div class="wb-eyebrow">Sales Dashboard</div>
                <div class="wb-name">
                  <?= $greeting ?>, <?= htmlspecialchars($agent['name']) ?> 👋
                </div>
                <div class="wb-meta">
                  <span class="wb-chip"><span class="dot"></span><?= ucfirst($agent['status']) ?> · <?= htmlspecialchars($agent['province']) ?></span>
                  <span class="wb-chip"><?= ucfirst($agent['salary_type']) ?> Agent</span>
                  <span class="wb-chip"><?= date('F Y') ?></span>
                </div>
              </div>
              <a href="create_tenant_subscription.php" class="btn-new-tenant">
                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M12 4v16m8-8H4"/></svg>
                New Tenant &amp; Subscription
              </a>
            </div>

            <!-- ── METRIC CARDS ── -->
            <p class="sec-label">Performance Overview</p>
            <div class="metric-grid">

              <!-- Active Tenants -->
              <div class="mcard">
                <div class="mcard-top">
                  <div class="mcard-icon" style="background:linear-gradient(135deg,rgba(64,153,255,0.10),rgba(46,216,182,0.10));">
                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="url(#g-blue-teal)" stroke-width="2">
                      <defs><linearGradient id="g-blue-teal" x1="0" y1="0" x2="1" y2="1"><stop offset="0%" stop-color="#4099ff"/><stop offset="100%" stop-color="#2ed8b6"/></linearGradient></defs>
                      <path stroke-linecap="round" stroke-linejoin="round" d="M17 20h5v-2a4 4 0 00-4-4h-1M9 20H4v-2a4 4 0 014-4h1m4-4a4 4 0 100-8 4 4 0 000 8z"/>
                    </svg>
                  </div>
                  <span class="mcard-badge" style="background:var(--teal-dim);color:#0d9373;">Live</span>
                </div>
                <div class="mcard-value grad-text"><?= $tenant_count ?></div>
                <div class="mcard-label">Active Tenants</div>
                <div class="mcard-sub">Travel agencies managed</div>
              </div>

              <?php if (in_array($agent['salary_type'], ['commission', 'both'])): ?>

              <!-- Total Earned -->
              <div class="mcard">
                <div class="mcard-top">
                  <div class="mcard-icon" style="background:var(--teal-dim);">
                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="#2ed8b6" stroke-width="2">
                      <path stroke-linecap="round" stroke-linejoin="round" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                    </svg>
                  </div>
                </div>
                <div class="mcard-value" style="color:var(--teal);">$<?= number_format($total_earned, 2) ?></div>
                <div class="mcard-label">Total Earned</div>
                <div class="mcard-sub">Approved &amp; paid commissions</div>
              </div>

              <!-- Pending -->
              <div class="mcard">
                <div class="mcard-top">
                  <div class="mcard-icon" style="background:var(--amber-dim);">
                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="#F59E0B" stroke-width="2">
                      <path stroke-linecap="round" stroke-linejoin="round" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/>
                    </svg>
                  </div>
                </div>
                <div class="mcard-value" style="color:var(--amber);">$<?= number_format($pending_commission, 2) ?></div>
                <div class="mcard-label">Pending Commission</div>
                <div class="mcard-sub">Awaiting approval</div>
              </div>

              <!-- This Month -->
              <div class="mcard">
                <div class="mcard-top">
                  <div class="mcard-icon" style="background:var(--blue-dim);">
                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="#4099ff" stroke-width="2">
                      <path stroke-linecap="round" stroke-linejoin="round" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/>
                    </svg>
                  </div>
                </div>
                <div class="mcard-value" style="color:var(--blue);">$<?= number_format($this_month_commission, 2) ?></div>
                <div class="mcard-label">This Month</div>
                <div class="mcard-sub"><?= date('F Y') ?></div>
              </div>

              <?php endif; ?>
            </div>

            <!-- ── AGENT DETAILS ── -->
            <p class="sec-label">Agent Details</p>
            <div class="info-grid">

              <!-- Profile -->
              <div class="panel">
                <div class="panel-head">
                  <span class="panel-title">Profile Information</span>
                  <a href="profile.php" class="edit-link">
                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" width="11" height="11" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M15.232 5.232l3.536 3.536m-2.036-5.036a2.5 2.5 0 113.536 3.536L6.5 21.036H3v-3.572L16.732 3.732z"/></svg>
                    Edit
                  </a>
                </div>
                <div class="field-row">
                  <div>
                    <div class="field-label">Email</div>
                    <div class="field-value" style="font-size:11px;"><?= htmlspecialchars($agent['email']) ?></div>
                  </div>
                  <div>
                    <div class="field-label">Phone</div>
                    <div class="field-value"><?= htmlspecialchars($agent['phone'] ?? 'N/A') ?></div>
                  </div>
                </div>
                <div class="field-row">
                  <div>
                    <div class="field-label">Province</div>
                    <div class="field-value"><?= htmlspecialchars($agent['province']) ?></div>
                  </div>
                  <div>
                    <div class="field-label">Region</div>
                    <div class="field-value"><?= htmlspecialchars($agent['region'] ?? 'N/A') ?></div>
                  </div>
                </div>
                <div class="field-row full">
                  <div>
                    <div class="field-label">Account Status</div>
                    <div style="margin-top:6px;">
                      <?php if ($agent['status'] === 'active'): ?>
                        <span class="badge-active"><span class="dot"></span> Active</span>
                      <?php else: ?>
                        <span class="badge-inactive"><?= ucfirst($agent['status']) ?></span>
                      <?php endif; ?>
                    </div>
                  </div>
                </div>
              </div>

              <!-- Compensation -->
              <div class="panel">
                <div class="panel-head">
                  <span class="panel-title">Compensation Details</span>
                  <span style="font-size:10px;color:var(--text-3);">Contact admin to update</span>
                </div>
                <div class="field-row">
                  <div>
                    <div class="field-label">Salary Type</div>
                    <div class="field-value"><?= ucfirst($agent['salary_type']) ?></div>
                  </div>
                  <div>
                    <div class="field-label">Base Salary</div>
                    <div class="field-value"><?= $agent['base_salary'] ? '$' . number_format($agent['base_salary'], 2) : 'N/A' ?></div>
                  </div>
                </div>
                <?php if (in_array($agent['salary_type'], ['commission', 'both'])): ?>
                <div class="field-row">
                  <div>
                    <div class="field-label">Commission Rate</div>
                    <div class="field-value grad-text" style="font-family:'Sora',sans-serif;font-size:20px;font-weight:700;"><?= $agent['commission_rate'] ?>%</div>
                  </div>
                  <div>
                    <div class="field-label">Avg Monthly Earnings</div>
                    <div class="field-value" style="color:var(--teal);">$<?= number_format($avg_monthly, 2) ?></div>
                  </div>
                </div>
                <?php endif; ?>
              </div>
            </div>

            <!-- ── SALARY INFO (salary / both) ── -->
            <?php if (in_array($agent['salary_type'], ['salary', 'both'])): ?>
            <p class="sec-label">Salary Information</p>
            <div class="salary-card">
              <div>
                <div class="salary-label">Monthly Base Salary</div>
                <div class="salary-value grad-text">$<?= number_format($agent['base_salary'], 2) ?></div>
              </div>
              <a href="salary_payments.php" class="btn-outline">
                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" width="14" height="14" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7"/></svg>
                View Salary Payments
              </a>
            </div>
            <?php endif; ?>

            <!-- ── ANNUAL SUMMARY (commission / both) ── -->
            <?php if (in_array($agent['salary_type'], ['commission', 'both'])): ?>
            <p class="sec-label">Annual Summary</p>
            <div class="stats-grid">
              <div class="stat-tile">
                <div class="stat-icon">
                  <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="#2ed8b6" stroke-width="2" width="20" height="20"><path stroke-linecap="round" stroke-linejoin="round" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"/></svg>
                </div>
                <div class="stat-value" style="color:var(--teal);">$<?= number_format($yearly_earned, 2) ?></div>
                <div class="stat-label">Earned This Year</div>
              </div>
              <div class="stat-tile">
                <div class="stat-icon">
                  <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="#4099ff" stroke-width="2" width="20" height="20"><path stroke-linecap="round" stroke-linejoin="round" d="M7 12l3-3 3 3 4-4M8 21l4-4 4 4M3 4h18M4 4h16v12a2 2 0 01-2 2H6a2 2 0 01-2-2V4z"/></svg>
                </div>
                <div class="stat-value grad-text">$<?= number_format($avg_monthly, 2) ?></div>
                <div class="stat-label">Average Monthly</div>
              </div>
              <div class="stat-tile">
                <div class="stat-icon">
                  <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="#2ed8b6" stroke-width="2" width="20" height="20"><path stroke-linecap="round" stroke-linejoin="round" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"/></svg>
                </div>
                <div class="stat-value" style="color:var(--blue);"><?= $commission_records ?></div>
                <div class="stat-label">Commission Records</div>
              </div>
            </div>
            <?php endif; ?>

            <!-- ── RECENT ACTIVITY ── -->
            <p class="sec-label">Recent Activity</p>
            <div class="two-col-grid<?= !in_array($agent['salary_type'], ['commission','both']) ? ' ' : '' ?>"
                 style="<?= !in_array($agent['salary_type'], ['commission','both']) ? 'grid-template-columns:1fr;' : '' ?>">

              <?php if (in_array($agent['salary_type'], ['commission', 'both'])): ?>
              <!-- Commission History -->
              <div class="panel">
                <div class="panel-head">
                  <span class="panel-title">Commission History</span>
                </div>
                <div class="panel-body">
                  <?php if (!empty($recent_commissions)): ?>
                    <?php foreach ($recent_commissions as $c): ?>
                      <div class="list-row">
                        <div class="row-icon" style="background:linear-gradient(135deg,rgba(64,153,255,0.08),rgba(46,216,182,0.08));color:var(--blue);">
                          <?= strtoupper(substr(date('M', strtotime($c['period_month'])), 0, 3)) ?>
                        </div>
                        <div class="row-body">
                          <div class="row-title"><?= date('F Y', strtotime($c['period_month'])) ?></div>
                          <div class="row-sub">Commission period</div>
                        </div>
                        <div class="row-right">
                          <div class="row-amount">$<?= number_format($c['commission_amount'], 2) ?></div>
                          <span class="pill pill-<?= $c['status'] ?>"><?= ucfirst($c['status']) ?></span>
                        </div>
                      </div>
                    <?php endforeach; ?>
                  <?php else: ?>
                    <p style="text-align:center;color:var(--text-3);padding:28px 0;margin:0;font-size:13px;">No commission history yet.</p>
                  <?php endif; ?>
                </div>
                <div class="panel-footer">
                  <a href="commissions.php" class="panel-link" style="justify-content:center;">
                    View All Commissions
                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" width="12" height="12" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7"/></svg>
                  </a>
                </div>
              </div>
              <?php endif; ?>

              <!-- Recent Tenants -->
              <div class="panel">
                <div class="panel-head">
                  <span class="panel-title">Recent Tenants</span>
                </div>
                <div class="panel-body">
                  <?php if (!empty($recent_tenants)): ?>
                    <?php foreach ($recent_tenants as $t): ?>
                      <div class="list-row">
                        <div class="tenant-avatar">
                          <?= strtoupper(substr($t['name'], 0, 2)) ?>
                        </div>
                        <div class="row-body">
                          <div class="row-title"><?= htmlspecialchars($t['name']) ?></div>
                          <div class="row-sub">Since <?= date('M Y', strtotime($t['subscription_start_date'])) ?></div>
                        </div>
                        <div class="row-right">
                          <div class="row-amount">$<?= number_format($t['commission_earned'], 2) ?></div>
                          <span class="pill pill-<?= $t['status'] === 'active' ? 'active' : 'inactive' ?>"><?= ucfirst($t['status']) ?></span>
                        </div>
                      </div>
                    <?php endforeach; ?>
                  <?php else: ?>
                    <p style="text-align:center;color:var(--text-3);padding:28px 0;margin:0;font-size:13px;">No tenants assigned yet.</p>
                  <?php endif; ?>
                </div>
                <div class="panel-footer">
                  <a href="tenants.php" class="panel-link" style="justify-content:center;">
                    View All Tenants
                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" width="12" height="12" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7"/></svg>
                  </a>
                </div>
              </div>

            </div><!-- /.two-col-grid -->

          </div><!-- /.sa-dash -->
        </div><!-- /.main-body -->
      </div>
    </div>
  </div>
</div>
<!-- [ Main Content ] end -->

<script>
  window.csrfToken = <?= json_encode($_SESSION['csrf_token']) ?>;
</script>
<script src="../assets/js/vendor-all.min.js"></script>
<script src="../assets/plugins/bootstrap/js/bootstrap.min.js"></script>
<script src="../assets/js/pcoded.min.js"></script>
</body>
</html>