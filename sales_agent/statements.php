<?php
session_start();
require_once '../includes/db.php';

// Set secure headers
header("X-XSS-Protection: 1; mode=block");
header("X-Content-Type-Options: nosniff");
header("X-Frame-Options: DENY");
header("Referrer-Policy: strict-origin-when-cross-origin");

// Check session timeout
$sessionTimeout = 30 * 60;
if (isset($_SESSION['last_activity']) && (time() - $_SESSION['last_activity'] > $sessionTimeout)) {
    session_unset();
    session_destroy();
    header('Location: ../login.php?timeout=1');
    exit();
}
$_SESSION['last_activity'] = time();

// Check if user is a sales agent
if (!isset($_SESSION['user_id']) || !isset($_SESSION['role']) || $_SESSION['role'] !== 'sales_agent') {
    header('Location: ../login.php');
    exit();
}

// Get sales agent info
$stmt = $pdo->prepare("SELECT id FROM sales_agents WHERE user_id = ?");
$stmt->execute([$_SESSION['user_id']]);
$agent = $stmt->fetch();

if (!$agent) {
    header('Location: ../login.php');
    exit();
}

$agent_id = $agent['id'];

// Check if agent has commission access
if ($agent['salary_type'] === 'salary') {
    // Redirect to dashboard if salary-only agent tries to access statements
    header('Location: dashboard.php');
    exit();
}

$report_type = $_GET['type'] ?? 'monthly';
$year = $_GET['year'] ?? date('Y');

// Get all years available
$stmt = $pdo->prepare("SELECT DISTINCT YEAR(period_month) as year FROM sales_agent_commissions 
                       WHERE sales_agent_id = ? ORDER BY year DESC");
$stmt->execute([$agent_id]);
$available_years = $stmt->fetchAll();

// Get monthly summary for selected year
$stmt = $pdo->prepare("SELECT 
                        DATE_FORMAT(period_month, '%m') as month,
                        MONTHNAME(period_month) as month_name,
                        base_amount, commission_rate, commission_amount, 
                        status
                       FROM sales_agent_commissions 
                       WHERE sales_agent_id = ? AND YEAR(period_month) = ?
                       ORDER BY period_month ASC");
$stmt->execute([$agent_id, $year]);
$monthly_data = $stmt->fetchAll();

// Get year summary
$stmt = $pdo->prepare("SELECT 
                        SUM(base_amount) as total_base,
                        COUNT(*) as months,
                        SUM(CASE WHEN status = 'paid' THEN commission_amount ELSE 0 END) as paid,
                        SUM(CASE WHEN status = 'approved' THEN commission_amount ELSE 0 END) as approved,
                        SUM(CASE WHEN status = 'pending' THEN commission_amount ELSE 0 END) as pending
                       FROM sales_agent_commissions 
                       WHERE sales_agent_id = ? AND YEAR(period_month) = ?");
$stmt->execute([$agent_id, $year]);
$year_summary = $stmt->fetch();
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

  .metric-value {
    font-size: 1.75rem; font-weight: 800; color: var(--text-primary);
    line-height: 1; margin-bottom: 4px; letter-spacing: -.02em;
  }
  .metric-label { font-size: .83rem; color: var(--text-secondary); font-weight: 500; margin-bottom: 2px; }
  .metric-sub   { font-size: .75rem; color: var(--text-muted); }

  /* ── Card SAAS ── */
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
  .lci-title { font-size: .88rem; font-weight: 600; color: var(--text-primary); }
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

  select, input[type="text"] {
    padding: 8px 12px; border: 1px solid var(--border);
    border-radius: var(--radius-sm); font-size: .85rem; color: var(--text-primary);
    background: var(--surface);
  }
  select:focus, input[type="text"]:focus {
    outline: none; border-color: var(--brand); box-shadow: 0 0 0 3px rgba(37, 99, 235, 0.1);
  }

  .btn {
    display: inline-flex; align-items: center; gap: 8px;
    padding: 10px 20px; border-radius: var(--radius-sm); font-weight: 600;
    border: none; cursor: pointer; text-decoration: none; font-size: .85rem;
    transition: all .15s;
  }
  .btn-primary {
    background: var(--brand); color: white;
  }
  .btn-primary:hover {
    background: var(--brand-dark); transform: translateY(-1px); box-shadow: var(--shadow);
  }
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
                  <h5 class="m-b-10">Earning Statements</h5>
                </div>
                <ul class="breadcrumb">
                  <li class="breadcrumb-item"><a href="dashboard.php"><i class="feather icon-home"></i></a></li>
                  <li class="breadcrumb-item"><a href="#!">Statements</a></li>
                </ul>
              </div>
            </div>
          </div>
        </div>

        <div class="main-body">
          <div class="page-wrapper sa-dashboard">

            <!-- ── Year Selector ── -->
            <p class="section-label">Select Year</p>
            <div class="card-saas" style="margin-bottom: 24px;">
              <div class="card-saas-body">
                <form method="GET" action="statements.php" style="display: flex; gap: 12px; align-items: flex-end;">
                  <div style="flex: 0 0 auto;">
                    <label for="year" style="display: block; font-size: .85rem; font-weight: 600; color: var(--text-primary); margin-bottom: 6px;">Year</label>
                    <select id="year" name="year" onchange="this.form.submit();" style="min-width: 120px;">
                      <?php foreach ($available_years as $y): ?>
                      <option value="<?= $y['year'] ?>" <?= $year == $y['year'] ? 'selected' : '' ?>>
                        <?= $y['year'] ?>
                      </option>
                      <?php endforeach; ?>
                    </select>
                  </div>
                </form>
              </div>
            </div>

            <!-- ── Year Summary ── -->
            <p class="section-label">Summary - <?= $year ?></p>
            <div class="metric-grid">
              <!-- Total Base -->
              <div class="metric-card blue">
                <div class="metric-top">
                  <div class="metric-icon">
                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                  </div>
                </div>
                <div class="metric-value">$<?= number_format($year_summary['total_base'] ?? 0, 2) ?></div>
                <div class="metric-label">Total Base</div>
                <div class="metric-sub">Commission basis</div>
              </div>

              <!-- Paid -->
              <div class="metric-card green">
                <div class="metric-top">
                  <div class="metric-icon">
                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                  </div>
                </div>
                <div class="metric-value">$<?= number_format($year_summary['paid'] ?? 0, 2) ?></div>
                <div class="metric-label">Paid Commission</div>
                <div class="metric-sub"><?= $year_summary['months'] ?? 0 ?> months</div>
              </div>

              <!-- Approved -->
              <div class="metric-card cyan">
                <div class="metric-top">
                  <div class="metric-icon">
                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 5v2m-4 4v2m4 0v2m-4-8h4M8 7a4 4 0 118 0 4 4 0 01-8 0z"/></svg>
                  </div>
                </div>
                <div class="metric-value">$<?= number_format($year_summary['approved'] ?? 0, 2) ?></div>
                <div class="metric-label">Approved</div>
                <div class="metric-sub">Awaiting payment</div>
              </div>

              <!-- Pending -->
              <div class="metric-card amber">
                <div class="metric-top">
                  <div class="metric-icon">
                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                  </div>
                </div>
                <div class="metric-value">$<?= number_format($year_summary['pending'] ?? 0, 2) ?></div>
                <div class="metric-label">Pending</div>
                <div class="metric-sub">Awaiting approval</div>
              </div>
            </div>

            <!-- ── Monthly Details ── -->
            <p class="section-label">Monthly Breakdown</p>
            <div class="card-saas">
              <div class="card-saas-header">
                <h6>Earnings by Month - <?= $year ?></h6>
              </div>
              <div class="card-saas-body">
                <?php if (!empty($monthly_data)): ?>
                  <?php foreach ($monthly_data as $data): ?>
                    <div class="list-card-item">
                      <div class="lci-icon" style="background:var(--info-light); color:var(--info); font-size:.7rem; font-weight:700;">
                        <?= strtoupper(substr($data['month_name'], 0, 3)) ?>
                      </div>
                      <div class="lci-body">
                        <div class="lci-title"><?= $data['month_name'] ?></div>
                        <div class="lci-sub"><?= $data['commission_rate'] ?>% · Base: $<?= number_format($data['base_amount'], 2) ?></div>
                      </div>
                      <div class="lci-right">
                        <div class="lci-amount">$<?= number_format($data['commission_amount'], 2) ?></div>
                        <span class="pill pill-<?= $data['status'] ?>"><?= ucfirst($data['status']) ?></span>
                      </div>
                    </div>
                  <?php endforeach; ?>
                <?php else: ?>
                  <p style="text-align:center; color:var(--text-muted); padding: 24px 0; margin:0; font-size:.88rem;">No commission data for <?= $year ?></p>
                <?php endif; ?>
              </div>
            </div>

            <!-- ── Export ── -->
            <p class="section-label">Actions</p>
            <div class="card-saas">
              <div class="card-saas-body">
                <button type="button" class="btn btn-primary" onclick="window.print();">
                  <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" width="16" height="16"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z"/></svg>
                  Print Statement
                </button>
                <p style="margin-top: 12px; font-size:.75rem; color:var(--text-muted);">
                  Contact your admin for export options or if you need any adjustments to your statement.
                </p>
              </div>
            </div>

          </div><!-- /.page-wrapper -->
        </div><!-- /.main-body -->
      </div>
    </div>
  </div>
</div>
<!-- [ Main Content ] end -->

<script src="../assets/js/vendor-all.min.js"></script>
<script src="../assets/plugins/bootstrap/js/bootstrap.min.js"></script>
<script src="../assets/js/pcoded.min.js"></script>
<style>
  @media print {
    .page-header, .breadcrumb, .btn, nav, .section-label { display: none; }
    .card-saas { box-shadow: none; border: 1px solid #ddd; }
  }
</style>
</body>
</html>
