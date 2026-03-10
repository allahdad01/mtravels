<?php
// ─── SESSION & SECURITY ───────────────────────────────────────────────────────
if (session_status() === PHP_SESSION_NONE) { session_start(); }

header("X-XSS-Protection: 1; mode=block");
header("X-Content-Type-Options: nosniff");
header("X-Frame-Options: DENY");
header("Content-Security-Policy: default-src 'self'; script-src 'self' 'unsafe-inline' https://cdn.jsdelivr.net; style-src 'self' 'unsafe-inline' https://fonts.googleapis.com https://cdn.jsdelivr.net https://cdn.datatables.net; font-src 'self' https://fonts.gstatic.com; img-src 'self' data:;");
header("Referrer-Policy: strict-origin-when-cross-origin");

$sessionTimeout = 30 * 60;
if (isset($_SESSION['last_activity']) && (time() - $_SESSION['last_activity'] > $sessionTimeout)) {
    session_unset(); session_destroy();
    header('Location: ../login.php?timeout=1'); exit();
}
$_SESSION['last_activity'] = time();

$allowed_roles = ['admin', 'finance', 'sales', 'umrah', 'staff'];
if (!isset($_SESSION['user_id']) || !in_array($_SESSION['role'], $allowed_roles)) {
    error_log("Unauthorized access attempt to email_analytics: " . ($_SESSION['user_id'] ?? 'unknown') . " - Role: " . ($_SESSION['role'] ?? 'unknown') . " - IP: " . $_SERVER['REMOTE_ADDR']);
    header('Location: ../login.php'); exit();
}
if (!isset($_SESSION['csrf_token'])) { $_SESSION['csrf_token'] = bin2hex(random_bytes(32)); }

require_once '../includes/InputValidator.php';
require_once '../includes/db.php';

// Get tenant and branch from session
$tenant_id = $_SESSION['tenant_id'] ?? null;
$branch_id = $_SESSION['branch_id'] ?? null;

// Get date range from URL parameters or set defaults
$startDate = isset($_GET['start_date']) ? $_GET['start_date'] : date('Y-m-d', strtotime('-30 days'));
$endDate = isset($_GET['end_date']) ? $_GET['end_date'] : date('Y-m-d');
$emailType = isset($_GET['email_type']) ? $_GET['email_type'] : 'all';

// Build redirect URL with current query parameters
$redirect_url = $_SERVER['PHP_SELF'];
if (!empty($_GET)) {
    $redirect_url .= '?' . http_build_query($_GET);
}

// Get email analytics data
function getEmailAnalytics($pdo, $tenant_id, $branch_id, $startDate, $endDate, $emailType) {
   $whereClause = "WHERE et.tenant_id = ? AND et.branch_id = ? AND DATE(et.sent_at) BETWEEN ? AND ?";
   $params = [$tenant_id, $branch_id, $startDate, $endDate];

    if ($emailType !== 'all') {
        $whereClause .= " AND et.email_type = ?";
        $params[] = $emailType;
    }

    // Total emails sent
    $stmt = $pdo->prepare("SELECT COUNT(*) as total_sent FROM email_tracking et $whereClause");
    $stmt->execute($params);
    $totalSent = $stmt->fetch()['total_sent'];

    // Total emails opened
    $stmt = $pdo->prepare("SELECT COUNT(*) as total_opened FROM email_tracking et $whereClause AND et.opened = 1");
    $stmt->execute($params);
    $totalOpened = $stmt->fetch()['total_opened'];

    // Open rate
    $openRate = $totalSent > 0 ? round(($totalOpened / $totalSent) * 100, 2) : 0;

    // Emails by type
    $stmt = $pdo->prepare("SELECT et.email_type, COUNT(*) as count, SUM(et.opened) as opened FROM email_tracking et $whereClause GROUP BY et.email_type ORDER BY count DESC");
    $stmt->execute($params);
    $emailsByType = $stmt->fetchAll();

    // Daily email stats
    $stmt = $pdo->prepare("SELECT DATE(et.sent_at) as date, COUNT(*) as sent, SUM(et.opened) as opened FROM email_tracking et $whereClause GROUP BY DATE(et.sent_at) ORDER BY date DESC");
    $stmt->execute($params);
    $dailyStats = $stmt->fetchAll();

    // Recent emails
    $stmt = $pdo->prepare("SELECT et.*, DATE(et.sent_at) as sent_date FROM email_tracking et $whereClause ORDER BY et.sent_at DESC LIMIT 50");
    $stmt->execute($params);
    $recentEmails = $stmt->fetchAll();

    return [
        'total_sent' => $totalSent,
        'total_opened' => $totalOpened,
        'open_rate' => $openRate,
        'emails_by_type' => $emailsByType,
        'daily_stats' => $dailyStats,
        'recent_emails' => $recentEmails
    ];
}

$analytics = getEmailAnalytics($pdo, $tenant_id, $branch_id, $startDate, $endDate, $emailType);

require_once '../api/dashboard/dashboard_handler.php';
?>
<?php include '../includes/header.php'; ?>

<link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&family=JetBrains+Mono:wght@400;600&display=swap" rel="stylesheet">
<link rel="stylesheet" href="../css/general/modal-styles.css">
<style>
:root {
  --primary:#4099ff;--primary-dark:#2563eb;--primary-light:#60a5fa;
  --accent:#2ed8b6;--accent-dark:#14b8a6;--accent-light:#5eead4;
  --violet:#7c3aed;--violet-light:#a78bfa;--indigo:#4f46e5;
  --sky:#0ea5e9;--emerald:#10b981;--amber:#f59e0b;
  --rose:#f43f5e;--orange:#f97316;--pink:#ec4899;--teal:#14b8a6;
  --bg:#f8fafc;--surface:#ffffff;--surface2:#f1f5f9;--surface3:#e2e8f0;
  --border:rgba(0,0,0,0.08);
  --text:#1e293b;--text-muted:#64748b;
  --grad-start:#4099ff;--grad-end:#2ed8b6;--grad:linear-gradient(135deg,var(--grad-start) 0%,var(--grad-end) 100%);
}
.pcoded-main-container{background:var(--bg)!important;}
.pcoded-content,.pcoded-inner-content{background:transparent!important;}
.dash-wrap{font-family:'Plus Jakarta Sans',sans-serif;color:var(--text);padding:28px 20px;position:relative;}
.dash-wrap::before{content:'';position:fixed;inset:0;pointer-events:none;z-index:0;background:radial-gradient(ellipse 80% 60% at 10% 0%,rgba(124,58,237,.15) 0%,transparent 60%),radial-gradient(ellipse 60% 50% at 90% 10%,rgba(14,165,233,.12) 0%,transparent 55%),radial-gradient(ellipse 50% 40% at 50% 100%,rgba(16,185,129,.08) 0%,transparent 50%);}
.dash-inner{position:relative;z-index:1;}
.sec-label{font-size:11px;font-weight:700;letter-spacing:1.5px;text-transform:uppercase;color:var(--text-muted);margin-bottom:14px;display:flex;align-items:center;gap:8px;}
.sec-label::after{content:'';flex:1;height:1px;background:var(--border);}
.d-card{background:var(--surface);border:1px solid var(--border);border-radius:20px;padding:24px;margin-bottom:22px;}
.d-card-header{display:flex;justify-content:space-between;align-items:center;margin-bottom:18px;flex-wrap:wrap;gap:12px;}
.d-card-title{font-size:15px;font-weight:800;display:flex;align-items:center;gap:10px;color:var(--text);}
.ci{width:32px;height:32px;border-radius:9px;display:flex;align-items:center;justify-content:center;font-size:13px;}
.ci-violet{background:rgba(124,58,237,.2);color:var(--violet-light);}
.ci-sky{background:rgba(14,165,233,.2);color:var(--sky);}
.ci-emerald{background:rgba(16,185,129,.2);color:var(--emerald);}
.ci-amber{background:rgba(245,158,11,.2);color:var(--amber);}
.ci-rose{background:rgba(244,63,94,.2);color:var(--rose);}

/* Header */
.dash-header{display:flex;justify-content:space-between;align-items:center;margin-bottom:26px;flex-wrap:wrap;gap:16px;}
.dash-header h1{font-size:24px;font-weight:800;letter-spacing:-.5px;background:var(--grad);-webkit-background-clip:text;-webkit-text-fill-color:transparent;}
.dash-header p{color:var(--text-muted);font-size:14px;margin-top:3px;}
.header-actions{display:flex;gap:10px;flex-wrap:wrap;}
.dbtn{display:inline-flex;align-items:center;gap:7px;padding:9px 16px;border-radius:10px;font-size:13px;font-weight:600;font-family:inherit;cursor:pointer;border:none;transition:all .2s;text-decoration:none;}
.dbtn-ghost{background:var(--surface2);color:var(--text);border:1px solid var(--border);}
.dbtn-ghost:hover{background:var(--surface3);transform:translateY(-1px);color:var(--text);}
.dbtn-primary{background:var(--grad);color:#fff;box-shadow:0 4px 20px rgba(64,153,255,.35);}
.dbtn-primary:hover{transform:translateY(-2px);color:#fff;}
.dbtn-info{background:var(--grad);color:#fff;box-shadow:0 4px 16px rgba(46,216,182,.3);}
.dbtn-info:hover{transform:translateY(-2px);color:#fff;}

/* Metrics Grid */
.metrics-grid{display:grid;grid-template-columns:repeat(3,1fr);gap:16px;margin-bottom:22px;}
.metric-card{background:var(--surface);border:1px solid var(--border);border-radius:20px;padding:24px;text-align:center;}
.metric-value{font-size:28px;font-weight:800;font-family:'JetBrains Mono',monospace;letter-spacing:-1px;margin-bottom:8px;background:var(--grad);-webkit-background-clip:text;-webkit-text-fill-color:transparent;}
.metric-label{font-size:13px;font-weight:600;color:var(--text-muted);}

/* Filter Section */
.filter-card{background:var(--surface);border:1px solid var(--border);border-radius:20px;padding:24px;margin-bottom:22px;}
.filter-row{display:flex;gap:16px;flex-wrap:wrap;align-items:flex-end;}
.filter-group{flex:1;min-width:200px;}
.filter-group label{display:block;font-size:12px;font-weight:700;color:var(--text-muted);text-transform:uppercase;letter-spacing:.5px;margin-bottom:8px;}
.fin-select{background:var(--surface2);border:1px solid var(--border);color:var(--text);border-radius:9px;padding:10px 14px;font-size:13px;font-weight:600;font-family:inherit;cursor:pointer;outline:none;transition:border-color .2s;width:100%;}
.fin-select:focus{border-color:rgba(124,58,237,.5);}

/* Table Styles */
.table-responsive{overflow-x:auto;}
.d-card .table-responsive{border-radius:0;box-shadow:none;margin:0 -24px -24px -24px;}
.d-table{margin-bottom:0;width:100%;border-collapse:collapse;}
.d-table thead{background:var(--surface2);}
.d-table thead th{border-bottom:1px solid var(--border);font-weight:700;font-size:12px;text-transform:uppercase;letter-spacing:.5px;color:var(--text-muted);padding:14px 20px;text-align:left;}
.d-table tbody tr{border-bottom:1px solid var(--border);transition:background-color .2s;}
.d-table tbody tr:last-child{border-bottom:none;}
.d-table tbody tr:hover{background-color:var(--surface2);}
.d-table tbody td{padding:14px 20px;font-size:13px;color:var(--text);white-space:nowrap;}

/* Badge Styles */
.badge{font-size:11px;padding:4px 12px;border-radius:20px;font-weight:600;display:inline-block;}
.badge-success{background:rgba(16,185,129,.15);color:var(--emerald);}
.badge-secondary{background:rgba(100,116,139,.15);color:var(--text-muted);}



.btn-secondary {
    border-radius: 25px;
    padding: 0.75rem 2rem;
    font-weight: 600;
    transition: all 0.3s ease;
}

.btn-warning {
    background: linear-gradient(135deg, #ffc107 0%, #ff8c00 100%);
    border: none;
    border-radius: 25px;
    padding: 0.75rem 2rem;
    font-weight: 600;
    transition: all 0.3s ease;
    color: #212529;
}

.btn-warning:hover {
    transform: translateY(-1px);
    box-shadow: 0 4px 12px rgba(255, 193, 7, 0.3);
}

.btn-danger {
    background: linear-gradient(135deg, #dc3545 0%, #c82333 100%);
    border: none;
    border-radius: 25px;
    padding: 0.75rem 2rem;
    font-weight: 600;
    transition: all 0.3s ease;
}

.btn-danger:hover {
    transform: translateY(-1px);
    box-shadow: 0 4px 12px rgba(220, 53, 69, 0.3);
}

.btn-info {
    background: linear-gradient(135deg, #17a2b8 0%, #138496 100%);
    border: none;
    border-radius: 25px;
    padding: 0.75rem 2rem;
    font-weight: 600;
    transition: all 0.3s ease;
}

.btn-info:hover {
    transform: translateY(-1px);
    box-shadow: 0 4px 12px rgba(23, 162, 184, 0.3);
}

.btn-success {
    background: linear-gradient(135deg, #28a745 0%, #218838 100%);
    border: none;
    border-radius: 25px;
    padding: 0.75rem 2rem;
    font-weight: 600;
    transition: all 0.3s ease;
}

.btn-success:hover {
    transform: translateY(-1px);
    box-shadow: 0 4px 12px rgba(40, 167, 69, 0.3);
}

.alert {
    border-radius: 10px;
    border: none;
    padding: 1rem 1.5rem;
}

.alert-info {
    background: linear-gradient(135deg, #d1ecf1 0%, #bee5eb 100%);
    color: #0c5460;
}

.alert-success {
    background: linear-gradient(135deg, #d4edda 0%, #c3e6cb 100%);
    color: #155724;
}

.alert-danger {
    background: linear-gradient(135deg, #f8d7da 0%, #f5c6cb 100%);
    color: #721c24;
}

.metric-value {
    font-size: 2rem;
    font-weight: bold;
    color: #4099ff;
}

.metric-label {
    color: #6c757d;
    font-size: 0.9rem;
}

.chart-container {
    position: relative;
    height: 300px;
}

.filter-section {
    background: #f8f9fa;
    padding: 1.5rem;
    border-radius: 10px;
    margin-bottom: 2rem;
}

.h2 {
    font-size: 2.5rem;
}

.h4 {
    font-size: 1.5rem;
}

.h5 {
    font-size: 1.25rem;
}

.h6 {
    font-size: 1rem;
}
</style>
    <!-- [ Main Content ] start -->
    <div class="pcoded-main-container">
        <div class="pcoded-content">
            <div class="pcoded-inner-content">
                <div class="dash-wrap">
                    <div class="dash-inner">
                        
                        <!-- Header -->
                        <div class="dash-header">
                            <div>
                                <h1><i class="fas fa-envelope-open-text"></i> Email Analytics</h1>
                                <p>Track and analyze email performance metrics</p>
                            </div>
                        </div>

                        <!-- Filter Card -->
                        <div class="filter-card">
                            <form method="GET">
                                <div class="filter-row">
                                    <div class="filter-group">
                                        <label for="start_date">Start Date</label>
                                        <input type="date" class="fin-select" id="start_date" name="start_date" value="<?php echo htmlspecialchars($startDate); ?>">
                                    </div>
                                    <div class="filter-group">
                                        <label for="end_date">End Date</label>
                                        <input type="date" class="fin-select" id="end_date" name="end_date" value="<?php echo htmlspecialchars($endDate); ?>">
                                    </div>
                                    <div class="filter-group">
                                        <label for="email_type">Email Type</label>
                                        <select class="fin-select" id="email_type" name="email_type">
                                            <option value="all" <?php echo $emailType === 'all' ? 'selected' : ''; ?>>All Types</option>
                                            <option value="ticket_notification" <?php echo $emailType === 'ticket_notification' ? 'selected' : ''; ?>>Ticket Notifications</option>
                                            <option value="account_notification" <?php echo $emailType === 'account_notification' ? 'selected' : ''; ?>>Account Notifications</option>
                                        </select>
                                    </div>
                                    <div class="filter-group" style="min-width:auto;">
                                        <button type="submit" class="dbtn dbtn-primary"><i class="fas fa-filter"></i> Apply</button>
                                        <a href="email_analytics.php" class="dbtn dbtn-ghost"><i class="fas fa-redo"></i> Reset</a>
                                    </div>
                                </div>
                            </form>
                        </div>

                        <!-- Metrics Grid -->
                        <div class="metrics-grid">
                            <div class="metric-card">
                                <div class="metric-value"><?php echo number_format($analytics['total_sent']); ?></div>
                                <div class="metric-label"><i class="fas fa-envelope"></i> Total Sent</div>
                            </div>
                            <div class="metric-card">
                                <div class="metric-value"><?php echo number_format($analytics['total_opened']); ?></div>
                                <div class="metric-label"><i class="fas fa-envelope-open"></i> Total Opened</div>
                            </div>
                            <div class="metric-card">
                                <div class="metric-value"><?php echo $analytics['open_rate']; ?>%</div>
                                <div class="metric-label"><i class="fas fa-chart-pie"></i> Open Rate</div>
                            </div>
                        </div>

                        <!-- Charts Row -->
                        <div style="display:grid;grid-template-columns:1fr 1fr;gap:22px;margin-bottom:22px;">
                            <div class="d-card" style="margin-bottom:0;">
                                <div class="d-card-header">
                                    <div class="d-card-title"><div class="ci ci-violet"><i class="fas fa-pie-chart"></i></div>Emails by Type</div>
                                </div>
                                <div style="position:relative;height:300px;">
                                    <canvas id="typeChart"></canvas>
                                </div>
                            </div>
                            <div class="d-card" style="margin-bottom:0;">
                                <div class="d-card-header">
                                    <div class="d-card-title"><div class="ci ci-sky"><i class="fas fa-chart-line"></i></div>Daily Activity</div>
                                </div>
                                <div style="position:relative;height:300px;">
                                    <canvas id="dailyChart"></canvas>
                                </div>
                            </div>
                        </div>

                        <!-- Recent Emails Table -->
                        <div class="d-card">
                            <div class="d-card-header">
                                <div class="d-card-title"><div class="ci ci-emerald"><i class="fas fa-history"></i></div>Recent Activity</div>
                            </div>
                            <div class="table-responsive">
                                <table class="d-table">
                                    <thead>
                                        <tr>
                                            <th>Date</th>
                                            <th>Recipient</th>
                                            <th>Type</th>
                                            <th>Status</th>
                                            <th>Opened At</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($analytics['recent_emails'] as $email): ?>
                                        <tr>
                                            <td><?php echo date('M d, Y H:i', strtotime($email['sent_at'])); ?></td>
                                            <td><?php echo htmlspecialchars($email['recipient_email']); ?></td>
                                            <td><?php echo htmlspecialchars($email['email_type']); ?></td>
                                            <td><span class="badge <?php echo $email['opened'] ? 'badge-success' : 'badge-secondary'; ?>"><?php echo $email['opened'] ? 'Opened' : 'Sent'; ?></span></td>
                                            <td><?php echo $email['opened_at'] ? date('M d, Y H:i', strtotime($email['opened_at'])) : '-'; ?></td>
                                        </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>

                    </div> <!-- dash-inner -->
                </div> <!-- dash-wrap -->
            </div> <!-- pcoded-inner-content -->
        </div> <!-- pcoded-content -->
    </div> <!-- pcoded-main-container -->

<!-- Chart.js -->
<script src="https://cdn.jsdelivr.net/npm/chart.js@3.9.1/dist/chart.min.js"></script>
<!-- Required Js -->
<script src="../assets/js/vendor-all.min.js"></script>
<script src="../assets/plugins/bootstrap/js/bootstrap.min.js"></script>
<script src="../assets/js/pcoded.min.js"></script>

<script>
// Emails by Type Chart
const typeData = <?php echo json_encode($analytics['emails_by_type']); ?>;
const typeChart = new Chart(document.getElementById('typeChart'), {
    type: 'doughnut',
    data: {
        labels: typeData.map(item => item.email_type),
        datasets: [{
            data: typeData.map(item => item.count),
            backgroundColor: ['#4099ff', '#2ed8b6', '#ffb64d', '#ff5370'],
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

// Daily Email Activity Chart
const dailyData = <?php echo json_encode($analytics['daily_stats']); ?>;
const dailyChart = new Chart(document.getElementById('dailyChart'), {
    type: 'line',
    data: {
        labels: dailyData.map(item => new Date(item.date).toLocaleDateString()),
        datasets: [{
            label: 'Emails Sent',
            data: dailyData.map(item => item.sent),
            borderColor: '#4099ff',
            backgroundColor: 'rgba(64, 153, 255, 0.1)',
            tension: 0.4
        }, {
            label: 'Emails Opened',
            data: dailyData.map(item => item.opened),
            borderColor: '#2ed8b6',
            backgroundColor: 'rgba(46, 216, 182, 0.1)',
            tension: 0.4
        }]
    },
    options: {
        responsive: true,
        maintainAspectRatio: false,
        scales: {
            y: {
                beginAtZero: true
            }
        }
    }
});

// DataTable removed
</script>

<?php include '../includes/admin_footer.php'; ?>