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
    error_log("Unauthorized access attempt to sales_agent dashboard: " . ($_SESSION['user_id'] ?? 'unknown') . " - IP: " . $_SERVER['REMOTE_ADDR']);
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
?>
<?php include 'includes/header_sales_agent.php'; ?>

<style>
  /* ── CSS Variables ── */
  :root {
    --brand:        #2563EB;
    --brand-light:  #EFF6FF;
    --brand-dark:   #1D4ED8;
    --success:      #16A34A;
    --success-light:#F0FDF4;
    --warning:      #D97706;
    --warning-light:#FFFBEB;
    --info:         #0891B2;
    --info-light:   #ECFEFF;
    --purple:       #7C3AED;
    --purple-light: #F5F3FF;
    --surface:      #FFFFFF;
    --bg:           #F8FAFC;
    --border:       #E2E8F0;
    --text-primary: #0F172A;
    --text-secondary:#64748B;
    --text-muted:   #94A3B8;
    --radius:       12px;
    --radius-sm:    8px;
    --shadow-sm:    0 1px 3px rgba(0,0,0,.06), 0 1px 2px rgba(0,0,0,.04);
    --shadow:       0 4px 6px -1px rgba(0,0,0,.07), 0 2px 4px -1px rgba(0,0,0,.04);
    --shadow-md:    0 10px 15px -3px rgba(0,0,0,.08), 0 4px 6px -2px rgba(0,0,0,.04);
  }

  /* ── Layout ── */
  .sa-dashboard { background: var(--bg); padding: 24px; }

  /* ── Welcome Banner ── */
  .welcome-banner {
    background: linear-gradient(135deg, var(--brand) 0%, var(--brand-dark) 60%, #1e3a8a 100%);
    border-radius: var(--radius);
    padding: 28px 32px;
    color: #fff;
    display: flex;
    align-items: center;
    justify-content: space-between;
    margin-bottom: 24px;
    position: relative;
    overflow: hidden;
  }
  .welcome-banner::before {
    content: '';
    position: absolute; top: -40px; right: -40px;
    width: 200px; height: 200px;
    background: rgba(255,255,255,.06);
    border-radius: 50%;
  }
  .welcome-banner::after {
    content: '';
    position: absolute; bottom: -60px; right: 100px;
    width: 160px; height: 160px;
    background: rgba(255,255,255,.04);
    border-radius: 50%;
  }
  .welcome-banner h4 { font-size: 1.4rem; font-weight: 700; margin: 0 0 4px; }
  .welcome-banner p  { margin: 0; opacity: .8; font-size: .92rem; }
  .welcome-banner .badge-status {
    display: inline-flex; align-items: center; gap: 6px;
    background: rgba(255,255,255,.18); border-radius: 20px;
    padding: 4px 12px; font-size: .78rem; margin-top: 10px;
  }
  .welcome-banner .badge-status span { width: 7px; height: 7px; background:#4ADE80; border-radius:50%; display:inline-block; }
  .btn-new-tenant {
    background: #fff; color: var(--brand);
    border: none; border-radius: var(--radius-sm);
    padding: 10px 20px; font-weight: 600; font-size: .88rem;
    display: inline-flex; align-items: center; gap: 8px;
    text-decoration: none; white-space: nowrap;
    box-shadow: 0 4px 12px rgba(0,0,0,.15);
    transition: transform .15s, box-shadow .15s;
    position: relative; z-index: 1;
  }
  .btn-new-tenant:hover { transform: translateY(-1px); box-shadow: 0 6px 16px rgba(0,0,0,.2); color: var(--brand-dark); text-decoration: none; }

  /* ── Section label ── */
  .section-label {
    font-size: .7rem; font-weight: 700; letter-spacing: .08em;
    text-transform: uppercase; color: var(--text-muted);
    margin-bottom: 12px;
  }

  /* ── Metric Cards ── */
  .metric-grid {
    display: grid;
    grid-template-columns: repeat(4, 1fr);
    gap: 16px;
    margin-bottom: 24px;
  }
  @media (max-width: 992px) { .metric-grid { grid-template-columns: repeat(2,1fr); } }
  @media (max-width: 576px)  { .metric-grid { grid-template-columns: 1fr; } }

  .metric-card {
    background: var(--surface);
    border: 1px solid var(--border);
    border-radius: var(--radius);
    padding: 20px;
    box-shadow: var(--shadow-sm);
    transition: box-shadow .2s, transform .2s;
    position: relative;
    overflow: hidden;
  }
  .metric-card:hover { box-shadow: var(--shadow-md); transform: translateY(-2px); }
  .metric-card::before {
    content: '';
    position: absolute; top: 0; left: 0;
    width: 4px; height: 100%;
    border-radius: 4px 0 0 4px;
  }
  .metric-card.blue::before   { background: var(--brand); }
  .metric-card.green::before  { background: var(--success); }
  .metric-card.amber::before  { background: var(--warning); }
  .metric-card.cyan::before   { background: var(--info); }

  .metric-top {
    display: flex; align-items: flex-start;
    justify-content: space-between; margin-bottom: 14px;
  }
  .metric-icon {
    width: 40px; height: 40px; border-radius: var(--radius-sm);
    display: flex; align-items: center; justify-content: center;
    flex-shrink: 0;
  }
  .metric-icon svg { width: 20px; height: 20px; }
  .metric-card.blue  .metric-icon { background: var(--brand-light);  color: var(--brand); }
  .metric-card.green .metric-icon { background: var(--success-light); color: var(--success); }
  .metric-card.amber .metric-icon { background: var(--warning-light); color: var(--warning); }
  .metric-card.cyan  .metric-icon { background: var(--info-light);    color: var(--info); }

  .metric-trend {
    font-size: .72rem; font-weight: 600; padding: 2px 8px;
    border-radius: 20px; background: var(--success-light); color: var(--success);
  }
  .metric-value {
    font-size: 1.75rem; font-weight: 800; color: var(--text-primary);
    line-height: 1; margin-bottom: 4px; letter-spacing: -.02em;
  }
  .metric-label { font-size: .83rem; color: var(--text-secondary); font-weight: 500; margin-bottom: 2px; }
  .metric-sub   { font-size: .75rem; color: var(--text-muted); }

  /* ── Info Cards Grid (Profile + Compensation) ── */
  .info-grid {
    display: grid; grid-template-columns: 1fr 1fr;
    gap: 16px; margin-bottom: 24px;
  }
  @media (max-width: 768px) { .info-grid { grid-template-columns: 1fr; } }

  .card-saas {
    background: var(--surface); border: 1px solid var(--border);
    border-radius: var(--radius); box-shadow: var(--shadow-sm);
    overflow: hidden;
  }
  .card-saas-header {
    padding: 16px 20px; border-bottom: 1px solid var(--border);
    display: flex; align-items: center; justify-content: space-between;
  }
  .card-saas-header h6 {
    margin: 0; font-size: .88rem; font-weight: 700; color: var(--text-primary);
  }
  .card-saas-body { padding: 20px; }

  /* Profile fields as chips */
  .field-grid {
    display: grid; grid-template-columns: 1fr 1fr; gap: 12px;
  }
  .field-chip {
    background: var(--bg); border: 1px solid var(--border);
    border-radius: var(--radius-sm); padding: 10px 14px;
  }
  .field-chip .fc-label {
    font-size: .7rem; font-weight: 600; text-transform: uppercase;
    letter-spacing: .06em; color: var(--text-muted); margin-bottom: 3px;
  }
  .field-chip .fc-value {
    font-size: .88rem; font-weight: 600; color: var(--text-primary);
  }
  .badge-active {
    display: inline-flex; align-items: center; gap: 5px;
    background: var(--success-light); color: var(--success);
    font-size: .75rem; font-weight: 600; padding: 3px 10px; border-radius: 20px;
  }
  .badge-active span { width: 6px; height: 6px; background: var(--success); border-radius: 50%; }
  .badge-inactive {
    display: inline-flex; align-items: center; gap: 5px;
    background: #F1F5F9; color: var(--text-secondary);
    font-size: .75rem; font-weight: 600; padding: 3px 10px; border-radius: 20px;
  }

  /* Compensation chips */
  .comp-rate-badge {
    display: inline-flex; align-items: center;
    background: var(--brand-light); color: var(--brand);
    font-size: 1.3rem; font-weight: 800;
    padding: 10px 20px; border-radius: var(--radius-sm);
    margin-bottom: 12px;
  }
  .comp-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 10px; }

  /* ── Quick Stats row ── */
  .stats-grid {
    display: grid; grid-template-columns: repeat(3,1fr);
    gap: 16px; margin-bottom: 24px;
  }
  @media (max-width: 768px) { .stats-grid { grid-template-columns: 1fr; } }

  .stat-card {
    background: var(--surface); border: 1px solid var(--border);
    border-radius: var(--radius); padding: 20px;
    box-shadow: var(--shadow-sm); text-align: center;
  }
  .stat-card .stat-icon {
    width: 44px; height: 44px; border-radius: 50%;
    display: flex; align-items: center; justify-content: center;
    margin: 0 auto 12px;
  }
  .stat-card .stat-value { font-size: 1.5rem; font-weight: 800; color: var(--text-primary); letter-spacing:-.02em; }
  .stat-card .stat-label { font-size: .8rem; color: var(--text-secondary); margin-top: 2px; }

  /* ── Commission + Tenant card lists ── */
  .two-col-grid {
    display: grid; grid-template-columns: 1fr 1fr;
    gap: 16px; margin-bottom: 24px;
  }
  @media (max-width: 768px) { .two-col-grid { grid-template-columns: 1fr; } }

  .list-card-item {
    display: flex; align-items: center; gap: 14px;
    padding: 14px 0;
    border-bottom: 1px solid var(--border);
  }
  .list-card-item:last-child { border-bottom: none; padding-bottom: 0; }
  .list-card-item:first-child { padding-top: 0; }

  .lci-icon {
    width: 38px; height: 38px; border-radius: var(--radius-sm);
    display: flex; align-items: center; justify-content: center;
    flex-shrink: 0; font-size: .85rem; font-weight: 700;
  }
  .lci-body { flex: 1; min-width: 0; }
  .lci-title { font-size: .88rem; font-weight: 600; color: var(--text-primary); white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
  .lci-sub   { font-size: .75rem; color: var(--text-muted); margin-top: 1px; }
  .lci-right { text-align: right; flex-shrink: 0; }
  .lci-amount { font-size: .95rem; font-weight: 700; color: var(--text-primary); }

  .pill {
    display: inline-block; font-size: .7rem; font-weight: 600;
    padding: 2px 9px; border-radius: 20px; margin-top: 3px;
  }
  .pill-paid    { background:#DCFCE7; color:#15803D; }
  .pill-approved{ background:#DBEAFE; color:#1D4ED8; }
  .pill-pending { background:#FEF3C7; color:#B45309; }
  .pill-active  { background:#DCFCE7; color:#15803D; }
  .pill-inactive{ background:#F1F5F9; color:#64748B; }

  .tenant-avatar {
    width: 38px; height: 38px; border-radius: var(--radius-sm);
    background: var(--brand-light); color: var(--brand);
    display: flex; align-items: center; justify-content: center;
    font-weight: 700; font-size: .85rem; flex-shrink: 0;
  }

  .btn-link-saas {
    display: inline-flex; align-items: center; gap: 6px;
    font-size: .8rem; font-weight: 600; color: var(--brand);
    border: 1px solid var(--brand-light); background: var(--brand-light);
    border-radius: var(--radius-sm); padding: 6px 14px;
    text-decoration: none; transition: background .15s;
    margin-top: 12px;
  }
  .btn-link-saas:hover { background: #DBEAFE; color: var(--brand-dark); text-decoration: none; }

  .card-footer-saas {
    padding: 12px 20px; border-top: 1px solid var(--border);
    background: var(--bg); text-align: center;
  }

  /* Scrollbar for card body */
  .card-saas-body.scrollable { max-height: 340px; overflow-y: auto; }
</style>

<!-- [ Main Content ] start -->
<div class="pcoded-main-container">
  <div class="pcoded-wrapper">
    <div class="pcoded-content">
      <div class="pcoded-inner-content">

        <!-- Breadcrumb -->
        <div class="page-header">
          <div class="page-block">
            <div class="row align-items-center">
              <div class="col-md-12">
                <div class="page-header-title">
                  <h5 class="m-b-10">Dashboard</h5>
                </div>
                <ul class="breadcrumb">
                  <li class="breadcrumb-item"><a href="dashboard.php"><i class="feather icon-home"></i></a></li>
                  <li class="breadcrumb-item"><a href="#!">Dashboard</a></li>
                </ul>
              </div>
            </div>
          </div>
        </div>

        <div class="main-body">
          <div class="page-wrapper sa-dashboard">

            <!-- ── Welcome Banner ── -->
            <div class="welcome-banner">
              <div>
                <h4>
                  <?php
                    $hour = date('H');
                    if ($hour < 12) echo "Good morning, ";
                    elseif ($hour < 18) echo "Good afternoon, ";
                    else echo "Good evening, ";
                    echo htmlspecialchars($agent['name']) . " 👋";
                  ?>
                </h4>
                <p>
                  <?php
                    if ($hour < 12) echo "Here's your sales overview for today.";
                    elseif ($hour < 18) echo "Keep pushing those sales!";
                    else echo "Check your performance metrics below.";
                  ?>
                </p>
                <div class="badge-status">
                  <span></span>
                  <?= ucfirst($agent['status']) ?> · <?= htmlspecialchars($agent['province']) ?>
                </div>
              </div>
              <a href="create_tenant_subscription.php" class="btn-new-tenant">
                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" width="16" height="16"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
                New Tenant & Subscription
              </a>
            </div>

            <!-- ── Metric Cards ── -->
            <p class="section-label">Performance Overview</p>
            <div class="metric-grid">

              <!-- Active Tenants -->
              <div class="metric-card blue">
                <div class="metric-top">
                  <div class="metric-icon">
                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a4 4 0 00-4-4h-1M9 20H4v-2a4 4 0 014-4h1m4-4a4 4 0 100-8 4 4 0 000 8z"/></svg>
                  </div>
                  <span class="metric-trend">Live</span>
                </div>
                <div class="metric-value"><?= $tenant_count ?></div>
                <div class="metric-label">Active Tenants</div>
                <div class="metric-sub">Travel agencies managed</div>
              </div>

              <?php if (in_array($agent['salary_type'], ['commission', 'both'])): ?>
              <!-- Total Earned -->
              <div class="metric-card green">
                <div class="metric-top">
                  <div class="metric-icon">
                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                  </div>
                </div>
                <div class="metric-value">$<?= number_format($total_earned, 2) ?></div>
                <div class="metric-label">Total Earned</div>
                <div class="metric-sub">Approved &amp; paid commissions</div>
              </div>

              <!-- Pending Commission -->
              <div class="metric-card amber">
                <div class="metric-top">
                  <div class="metric-icon">
                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                  </div>
                </div>
                <div class="metric-value">$<?= number_format($pending_commission, 2) ?></div>
                <div class="metric-label">Pending Commission</div>
                <div class="metric-sub">Awaiting approval</div>
              </div>

              <!-- This Month -->
              <div class="metric-card cyan">
                <div class="metric-top">
                  <div class="metric-icon">
                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
                  </div>
                </div>
                <div class="metric-value">$<?= number_format($this_month_commission, 2) ?></div>
                <div class="metric-label">This Month</div>
                <div class="metric-sub"><?= date('F Y') ?></div>
              </div>
              <?php endif; ?>
            </div>

            <!-- ── Profile + Compensation ── -->
            <p class="section-label">Agent Details</p>
            <div class="info-grid">

              <!-- Profile -->
              <div class="card-saas">
                <div class="card-saas-header">
                  <h6>Profile Information</h6>
                  <a href="profile.php" class="btn-link-saas" style="margin-top:0; font-size:.75rem; padding: 4px 10px;">
                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" width="13" height="13"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/></svg>
                    Edit
                  </a>
                </div>
                <div class="card-saas-body">
                  <div class="field-grid">
                    <div class="field-chip">
                      <div class="fc-label">Email</div>
                      <div class="fc-value" style="font-size:.82rem;"><?= htmlspecialchars($agent['email']) ?></div>
                    </div>
                    <div class="field-chip">
                      <div class="fc-label">Phone</div>
                      <div class="fc-value"><?= htmlspecialchars($agent['phone'] ?? 'N/A') ?></div>
                    </div>
                    <div class="field-chip">
                      <div class="fc-label">Province</div>
                      <div class="fc-value"><?= htmlspecialchars($agent['province']) ?></div>
                    </div>
                    <div class="field-chip">
                      <div class="fc-label">Region</div>
                      <div class="fc-value"><?= htmlspecialchars($agent['region'] ?? 'N/A') ?></div>
                    </div>
                    <div class="field-chip" style="grid-column: 1 / -1;">
                      <div class="fc-label">Account Status</div>
                      <div class="fc-value" style="margin-top:4px;">
                        <?php if ($agent['status'] === 'active'): ?>
                          <span class="badge-active"><span></span> Active</span>
                        <?php else: ?>
                          <span class="badge-inactive"><?= ucfirst($agent['status']) ?></span>
                        <?php endif; ?>
                      </div>
                    </div>
                  </div>
                </div>
              </div>

              <!-- Compensation -->
              <div class="card-saas">
                <div class="card-saas-header">
                  <h6>Compensation Details</h6>
                  <span style="font-size:.75rem; color:var(--text-muted);">Contact admin to update</span>
                </div>
                <div class="card-saas-body">
                  <div class="comp-grid">
                    <div class="field-chip">
                      <div class="fc-label">Salary Type</div>
                      <div class="fc-value"><?= ucfirst($agent['salary_type']) ?></div>
                    </div>
                    <div class="field-chip">
                      <div class="fc-label">Base Salary</div>
                      <div class="fc-value"><?= $agent['base_salary'] ? '$' . number_format($agent['base_salary'], 2) : 'N/A' ?></div>
                    </div>
                    <?php if (in_array($agent['salary_type'], ['commission', 'both'])): ?>
                    <div class="field-chip">
                      <div class="fc-label">Commission Rate</div>
                      <div class="fc-value"><?= $agent['commission_rate'] ?>%</div>
                    </div>
                    <div class="field-chip">
                      <div class="fc-label">Avg Monthly Earnings</div>
                      <div class="fc-value" style="color: var(--success);">$<?= number_format($avg_monthly, 2) ?></div>
                    </div>
                    <?php endif; ?>
                  </div>
                </div>
              </div>
            </div>

            <?php if (in_array($agent['salary_type'], ['salary', 'both'])): ?>
            <!-- ── Salary Info ── -->
            <p class="section-label">Salary Information</p>
            <div class="card-saas" style="margin-bottom: 24px;">
              <div class="card-saas-body">
                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px;">
                  <div>
                    <div style="font-size: .7rem; font-weight: 700; letter-spacing: .08em; text-transform: uppercase; color: var(--text-muted); margin-bottom: 8px;">Monthly Base Salary</div>
                    <div style="font-size: 1.75rem; font-weight: 800; color: var(--text-primary); letter-spacing: -.02em;">$<?= number_format($agent['base_salary'], 2) ?></div>
                  </div>
                  <div>
                    <a href="salary_payments.php" style="display: inline-flex; align-items: center; gap: 6px; font-size: .8rem; font-weight: 600; color: var(--brand); border: 1px solid var(--brand-light); background: var(--brand-light); border-radius: var(--radius-sm); padding: 8px 14px; text-decoration: none; transition: background .15s;">
                      <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" width="14" height="14"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
                      View Salary Payments
                    </a>
                  </div>
                </div>
              </div>
            </div>
            <?php endif; ?>

            <?php if (in_array($agent['salary_type'], ['commission', 'both'])): ?>
            <!-- ── Quick Stats ── -->
            <p class="section-label">Annual Summary</p>
            <div class="stats-grid">
              <div class="stat-card">
                <div class="stat-icon" style="background:var(--success-light);">
                  <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" width="22" height="22" style="color:var(--success)"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"/></svg>
                </div>
                <div class="stat-value" style="color:var(--success);">$<?= number_format($yearly_earned, 2) ?></div>
                <div class="stat-label">Earned This Year</div>
              </div>
              <div class="stat-card">
                <div class="stat-icon" style="background:var(--info-light);">
                  <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" width="22" height="22" style="color:var(--info)"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 12l3-3 3 3 4-4M8 21l4-4 4 4M3 4h18M4 4h16v12a2 2 0 01-2 2H6a2 2 0 01-2-2V4z"/></svg>
                </div>
                <div class="stat-value" style="color:var(--info);">$<?= number_format($avg_monthly, 2) ?></div>
                <div class="stat-label">Average Monthly</div>
              </div>
              <div class="stat-card">
                <div class="stat-icon" style="background:var(--purple-light);">
                  <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" width="22" height="22" style="color:var(--purple)"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"/></svg>
                </div>
                <div class="stat-value" style="color:var(--purple);"><?= $commission_records ?></div>
                <div class="stat-label">Commission Records</div>
              </div>
            </div>
            <?php endif; ?>

            <?php if (in_array($agent['salary_type'], ['commission', 'both'])): ?>
            <!-- ── Commission History + Recent Tenants ── -->
            <p class="section-label">Recent Activity</p>
            <div class="two-col-grid">

              <!-- Commission History -->
              <div class="card-saas">
                <div class="card-saas-header">
                  <h6>Commission History</h6>
                </div>
                <div class="card-saas-body">
                  <?php if (!empty($recent_commissions)): ?>
                    <?php foreach ($recent_commissions as $c): ?>
                      <div class="list-card-item">
                        <div class="lci-icon" style="background:var(--brand-light); color:var(--brand);">
                          <?= strtoupper(substr(date('M', strtotime($c['period_month'])), 0, 3)) ?>
                        </div>
                        <div class="lci-body">
                          <div class="lci-title"><?= date('F Y', strtotime($c['period_month'])) ?></div>
                          <div class="lci-sub">Commission period</div>
                        </div>
                        <div class="lci-right">
                          <div class="lci-amount">$<?= number_format($c['commission_amount'], 2) ?></div>
                          <span class="pill pill-<?= $c['status'] ?>"><?= ucfirst($c['status']) ?></span>
                        </div>
                      </div>
                    <?php endforeach; ?>
                  <?php else: ?>
                    <p style="text-align:center; color:var(--text-muted); padding: 24px 0; margin:0; font-size:.88rem;">No commission history yet.</p>
                  <?php endif; ?>
                </div>
                <div class="card-footer-saas">
                  <a href="commissions.php" class="btn-link-saas" style="margin-top:0;">
                    View All Commissions
                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" width="14" height="14"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
                  </a>
                </div>
              </div>
            <?php else: ?>
            <!-- ── Recent Tenants Only ── -->
            <p class="section-label">Recent Activity</p>
            <div class="two-col-grid" style="grid-template-columns: 1fr;">
            <?php endif; ?>

              <!-- Recent Tenants -->
              <div class="card-saas">
                <div class="card-saas-header">
                  <h6>Recent Tenants</h6>
                </div>
                <div class="card-saas-body">
                  <?php if (!empty($recent_tenants)): ?>
                    <?php foreach ($recent_tenants as $t): ?>
                      <div class="list-card-item">
                        <div class="tenant-avatar">
                          <?= strtoupper(substr($t['name'], 0, 2)) ?>
                        </div>
                        <div class="lci-body">
                          <div class="lci-title"><?= htmlspecialchars($t['name']) ?></div>
                          <div class="lci-sub">Since <?= date('M Y', strtotime($t['subscription_start_date'])) ?></div>
                        </div>
                        <div class="lci-right">
                          <div class="lci-amount">$<?= number_format($t['commission_earned'], 2) ?></div>
                          <span class="pill pill-<?= $t['status'] === 'active' ? 'active' : 'inactive' ?>"><?= ucfirst($t['status']) ?></span>
                        </div>
                      </div>
                    <?php endforeach; ?>
                  <?php else: ?>
                    <p style="text-align:center; color:var(--text-muted); padding: 24px 0; margin:0; font-size:.88rem;">No tenants assigned yet.</p>
                  <?php endif; ?>
                </div>
                <div class="card-footer-saas">
                  <a href="tenants.php" class="btn-link-saas" style="margin-top:0;">
                    View All Tenants
                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" width="14" height="14"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
                  </a>
                </div>
              </div>
            </div>

          </div><!-- /.page-wrapper -->
        </div><!-- /.main-body -->
      </div>
    </div>
  </div>
</div>
<!-- [ Main Content ] end -->

<script>
    // Make CSRF token available to JavaScript
    window.csrfToken = <?= json_encode($_SESSION['csrf_token']) ?>;
</script>

<script src="../assets/js/vendor-all.min.js"></script>
<script src="../assets/plugins/bootstrap/js/bootstrap.min.js"></script>
<script src="../assets/js/pcoded.min.js"></script>
</body>
</html>