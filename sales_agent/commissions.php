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
$stmt = $pdo->prepare("SELECT id, name FROM sales_agents WHERE user_id = ?");
$stmt->execute([$_SESSION['user_id']]);
$agent = $stmt->fetch();

if (!$agent) {
    header('Location: ../login.php');
    exit();
}

$agent_id = $agent['id'];

// Check if agent has commission access
if ($agent['salary_type'] === 'salary') {
    // Redirect to dashboard if salary-only agent tries to access commissions
    header('Location: dashboard.php');
    exit();
}

// Pagination
$items_per_page = 12;
$current_page = intval($_GET['page'] ?? 1);
$status_filter = $_GET['status'] ?? '';

// Count total commissions
$count_query = "SELECT COUNT(*) as total FROM sales_agent_commissions WHERE sales_agent_id = ?";
$filter_params = [$agent_id];

if (!empty($status_filter)) {
    $count_query .= " AND status = ?";
    $filter_params[] = $status_filter;
}

$stmt = $pdo->prepare($count_query);
$stmt->execute($filter_params);
$total_items = $stmt->fetch()['total'];
$total_pages = ceil($total_items / $items_per_page);
$current_page = max(1, min($current_page, $total_pages));
$offset = ($current_page - 1) * $items_per_page;

// Fetch commissions
$query = "SELECT * FROM sales_agent_commissions WHERE sales_agent_id = ?";
$query_params = [$agent_id];

if (!empty($status_filter)) {
    $query .= " AND status = ?";
    $query_params[] = $status_filter;
}

$query .= " ORDER BY period_month DESC LIMIT ? OFFSET ?";
$query_params[] = $items_per_page;
$query_params[] = $offset;

$stmt = $pdo->prepare($query);
$stmt->execute($query_params);
$commissions = $stmt->fetchAll();

// Get summary stats
$stmt = $pdo->prepare("SELECT 
                        SUM(CASE WHEN status IN ('approved', 'paid') THEN commission_amount ELSE 0 END) as earned,
                        SUM(CASE WHEN status = 'pending' THEN commission_amount ELSE 0 END) as pending,
                        SUM(CASE WHEN status = 'paid' THEN commission_amount ELSE 0 END) as paid,
                        COUNT(*) as total_records
                       FROM sales_agent_commissions WHERE sales_agent_id = ?");
$stmt->execute([$agent_id]);
$stats = $stmt->fetch();
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
  .card-footer-saas {
    padding: 12px 20px; border-top: 1px solid var(--border);
    background: var(--bg); text-align: center;
  }

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

  .btn-link-saas {
    display: inline-flex; align-items: center; gap: 6px;
    font-size: .8rem; font-weight: 600; color: var(--brand);
    border: 1px solid var(--brand-light); background: var(--brand-light);
    border-radius: var(--radius-sm); padding: 6px 14px;
    text-decoration: none; transition: background .15s;
    margin-top: 12px;
  }
  .btn-link-saas:hover { background: #DBEAFE; color: var(--brand-dark); text-decoration: none; }

  .filter-button {
    display: inline-flex; align-items: center; gap: 6px;
    font-size: .8rem; font-weight: 600; padding: 6px 14px;
    border-radius: var(--radius-sm); text-decoration: none; 
    margin-right: 8px; margin-bottom: 8px;
    border: 1px solid var(--border); background: var(--surface);
    color: var(--text-primary); transition: all .15s;
  }
  .filter-button.active {
    background: var(--brand); color: white; border-color: var(--brand);
  }
  .filter-button:hover { background: var(--brand); color: white; border-color: var(--brand); text-decoration: none; }
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
                  <h5 class="m-b-10">Commission History</h5>
                </div>
                <ul class="breadcrumb">
                  <li class="breadcrumb-item"><a href="dashboard.php"><i class="feather icon-home"></i></a></li>
                  <li class="breadcrumb-item"><a href="#!">Commissions</a></li>
                </ul>
              </div>
            </div>
          </div>
        </div>

        <div class="main-body">
          <div class="page-wrapper sa-dashboard">

            <!-- ── Summary Metric Cards ── -->
            <p class="section-label">Commission Summary</p>
            <div class="metric-grid">
              <!-- Total Earned -->
              <div class="metric-card green">
                <div class="metric-top">
                  <div class="metric-icon">
                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                  </div>
                </div>
                <div class="metric-value">$<?= number_format($stats['earned'] ?? 0, 2) ?></div>
                <div class="metric-label">Total Earned</div>
                <div class="metric-sub">Approved &amp; paid</div>
              </div>

              <!-- Pending Commission -->
              <div class="metric-card amber">
                <div class="metric-top">
                  <div class="metric-icon">
                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                  </div>
                </div>
                <div class="metric-value">$<?= number_format($stats['pending'] ?? 0, 2) ?></div>
                <div class="metric-label">Pending Commission</div>
                <div class="metric-sub">Awaiting approval</div>
              </div>

              <!-- Total Paid -->
              <div class="metric-card blue">
                <div class="metric-top">
                  <div class="metric-icon">
                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                  </div>
                </div>
                <div class="metric-value">$<?= number_format($stats['paid'] ?? 0, 2) ?></div>
                <div class="metric-label">Total Paid</div>
                <div class="metric-sub">Payments received</div>
              </div>

              <!-- Total Records -->
              <div class="metric-card cyan">
                <div class="metric-top">
                  <div class="metric-icon">
                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"/></svg>
                  </div>
                </div>
                <div class="metric-value"><?= $stats['total_records'] ?? 0 ?></div>
                <div class="metric-label">Commission Records</div>
                <div class="metric-sub">Total months tracked</div>
              </div>
            </div>

            <!-- ── Filter & List ── -->
            <p class="section-label">Commission Details</p>
            <div class="card-saas">
              <div class="card-saas-header">
                <h6>All Commissions</h6>
              </div>
              <div class="card-saas-body">
                <!-- Filter buttons -->
                <div style="margin-bottom: 20px;">
                  <a href="commissions.php" class="filter-button <?= empty($status_filter) ? 'active' : '' ?>">All</a>
                  <a href="?status=pending" class="filter-button <?= $status_filter == 'pending' ? 'active' : '' ?>">Pending</a>
                  <a href="?status=approved" class="filter-button <?= $status_filter == 'approved' ? 'active' : '' ?>">Approved</a>
                  <a href="?status=paid" class="filter-button <?= $status_filter == 'paid' ? 'active' : '' ?>">Paid</a>
                </div>

                <!-- Commission list -->
                <?php if (!empty($commissions)): ?>
                  <?php foreach ($commissions as $c): ?>
                    <div class="list-card-item">
                      <div class="lci-icon" style="background:var(--brand-light); color:var(--brand);">
                        <?= strtoupper(substr(date('M', strtotime($c['period_month'])), 0, 3)) ?>
                      </div>
                      <div class="lci-body">
                        <div class="lci-title"><?= date('F Y', strtotime($c['period_month'])) ?></div>
                        <div class="lci-sub"><?= $c['commission_rate'] ?>% · Base: $<?= number_format($c['base_amount'], 2) ?></div>
                      </div>
                      <div class="lci-right">
                        <div class="lci-amount">$<?= number_format($c['commission_amount'], 2) ?></div>
                        <span class="pill pill-<?= $c['status'] ?>"><?= ucfirst($c['status']) ?></span>
                      </div>
                    </div>
                  <?php endforeach; ?>
                <?php else: ?>
                  <p style="text-align:center; color:var(--text-muted); padding: 24px 0; margin:0; font-size:.88rem;">No commission records found.</p>
                <?php endif; ?>
              </div>

              <!-- Pagination -->
              <?php if ($total_pages > 1): ?>
              <div style="padding: 12px 20px; border-top: 1px solid var(--border); background: var(--bg); text-align: center;">
                <div style="display: flex; gap: 8px; justify-content: center; flex-wrap: wrap;">
                  <?php if ($current_page > 1): ?>
                    <a href="?page=<?= $current_page - 1 ?><?= !empty($status_filter) ? '&status=' . urlencode($status_filter) : '' ?>" class="filter-button">← Previous</a>
                  <?php endif; ?>
                  
                  <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                    <a href="?page=<?= $i ?><?= !empty($status_filter) ? '&status=' . urlencode($status_filter) : '' ?>" class="filter-button <?= $i === $current_page ? 'active' : '' ?>"><?= $i ?></a>
                  <?php endfor; ?>
                  
                  <?php if ($current_page < $total_pages): ?>
                    <a href="?page=<?= $current_page + 1 ?><?= !empty($status_filter) ? '&status=' . urlencode($status_filter) : '' ?>" class="filter-button">Next →</a>
                  <?php endif; ?>
                </div>
              </div>
              <?php endif; ?>
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
</body>
</html>
