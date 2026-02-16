<?php
// Include database security module for input validation
require_once 'includes/db_security.php';

// Include security module
require_once 'security.php';

// Include language helper
require_once '../includes/language_helpers.php';

// Enforce authentication
enforce_auth();

$tenant_id = $_SESSION['tenant_id'];
$branch_id = $_SESSION['branch_id'];
// Check if user is logged in
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header('Location: ../login.php');
    exit();
}

require_once '../includes/db.php';

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

include '../includes/header.php';
?>

<style>
/* Enhanced custom styles for better layout and design */
.page-header.card {
    background: linear-gradient(135deg, #4099ff 0%, #2ed8b6 100%);
    color: #ffffff;
    border: none;
    margin-bottom: 20px;
    padding: 20px !important;
    box-shadow: 0 4px 12px rgba(0,0,0,0.15);
    border-radius: 10px;
}

.page-header.card .row {
    display: flex;
    justify-content: space-between;
    align-items: center;
}

.page-header.card h5 {
    color: #ffffff;
    margin: 0;
    font-weight: 600;
}

.page-header.card .text-end {
    text-align: right;
}

.page-header.card .btn {
    background: rgba(255,255,255,0.2);
    color: #ffffff;
    border: 1px solid rgba(255,255,255,0.3);
    border-radius: 25px;
    transition: all 0.3s ease;
}

.page-header.card .btn:hover {
    background: rgba(255,255,255,0.3);
    border-color: rgba(255,255,255,0.5);
    transform: translateY(-1px);
}

.card {
    border-radius: 10px;
    box-shadow: 0 2px 8px rgba(0,0,0,0.1);
    transition: transform 0.2s ease-in-out, box-shadow 0.2s ease-in-out;
    border: none;
}

.card:hover {
    transform: translateY(-2px);
    box-shadow: 0 4px 16px rgba(0,0,0,0.15);
}

.card-header {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    color: white;
    border-radius: 10px 10px 0 0;
    padding: 1rem 1.5rem;
    border: none;
}

.card-header h5 {
    margin: 0;
    font-weight: 600;
    display: flex;
    align-items: center;
}

.progress {
    border-radius: 15px;
    overflow: hidden;
    box-shadow: inset 0 1px 2px rgba(0,0,0,0.1);
}

.progress-bar {
    transition: width 0.6s ease;
}

.badge {
    font-size: 0.85em;
    padding: 0.5em 0.75em;
    border-radius: 20px;
    font-weight: 500;
}

.badge-success {
    background-color: #28a745;
}

.badge-warning {
    background-color: #ffc107;
    color: #212529;
}

.badge-info {
    background-color: #17a2b8;
}

.badge-primary {
    background-color: #007bff;
}

.badge-secondary {
    background-color: #6c757d;
}

.badge-danger {
    background-color: #dc3545;
}

.table-responsive {
    border-radius: 10px;

}

.table {
    margin-bottom: 0;
}

.table thead th {
    background-color: #f8f9fa;
    border-bottom: 2px solid #dee2e6;
    font-weight: 600;
    color: #495057;
    padding: 1rem;
}

.table tbody tr:hover {
    background-color: #f1f3f4;
}

.table tbody td {
    padding: 1rem;
    vertical-align: middle;
}

.form-control {
    border-radius: 8px;
    border: 1px solid #ced4da;
    transition: border-color 0.15s ease-in-out, box-shadow 0.15s ease-in-out;
    padding: 0.75rem;
}

.form-control:focus {
    border-color: #4099ff;
    box-shadow: 0 0 0 0.2rem rgba(64, 153, 255, 0.25);
}

.btn-primary {
    background: linear-gradient(135deg, #4099ff 0%, #2ed8b6 100%);
    border: none;
    border-radius: 25px;
    padding: 0.75rem 2rem;
    font-weight: 600;
    transition: all 0.3s ease;
}

.btn-primary:hover {
    transform: translateY(-1px);
    box-shadow: 0 4px 12px rgba(64, 153, 255, 0.3);
}

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
                <div class="main-body">
                    <div class="page-wrapper">
                        <!-- [ Main Content ] start -->
                        <div class="main-content">
            <!-- [ breadcrumb ] start -->
            <div class="container-fluid">
                <div class="row">
                    <div class="col-12">
                        <div class="page-header card">
                            <div class="row align-items-center">
                                <div class="col-md-6">
                                    <h5 class="mb-0"><i class="feather icon-mail mr-2"></i>Email Analytics</h5>
                                </div>
                            </div>
                        </div>

                        <!-- Filter Section -->
                        <div class="filter-section">
                            <form method="GET" class="row g-3">
                                <div class="col-md-3">
                                    <label for="start_date" class="form-label">Start Date</label>
                                    <input type="date" class="form-control" id="start_date" name="start_date" value="<?php echo htmlspecialchars($startDate); ?>">
                                </div>
                                <div class="col-md-3">
                                    <label for="end_date" class="form-label">End Date</label>
                                    <input type="date" class="form-control" id="end_date" name="end_date" value="<?php echo htmlspecialchars($endDate); ?>">
                                </div>
                                <div class="col-md-3">
                                    <label for="email_type" class="form-label">Email Type</label>
                                    <select class="form-control" id="email_type" name="email_type">
                                        <option value="all" <?php echo $emailType === 'all' ? 'selected' : ''; ?>>All Types</option>
                                        <option value="ticket_notification" <?php echo $emailType === 'ticket_notification' ? 'selected' : ''; ?>>Ticket Notifications</option>
                                        <option value="account_notification" <?php echo $emailType === 'account_notification' ? 'selected' : ''; ?>>Account Notifications</option>
                                    </select>
                                </div>
                                <div class="col-md-3 d-flex align-items-end">
                                    <button type="submit" class="btn btn-primary mr-2">
                                        <i class="feather icon-filter mr-1"></i>Apply Filters
                                    </button>
                                    <a href="email_analytics.php" class="btn btn-secondary">
                                        <i class="feather icon-refresh-cw mr-1"></i>Reset
                                    </a>
                                </div>
                            </form>
                        </div>

                        <!-- Metrics Cards -->
                        <div class="row mb-4">
                            <div class="col-md-4">
                                <div class="card">
                                    <div class="card-body text-center">
                                        <div class="metric-value"><?php echo number_format($analytics['total_sent']); ?></div>
                                        <div class="metric-label">Total Emails Sent</div>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="card">
                                                                     <div class="card-body text-center">
                                                                         <div class="metric-value"><?php echo number_format($analytics['total_opened']); ?></div>
                                                                         <div class="metric-label">Total Emails Opened</div>
                                                                     </div>
                                                                 </div>
                            </div>
                            <div class="col-md-4">
                                <div class="card">
                                                                     <div class="card-body text-center">
                                                                         <div class="metric-value"><?php echo $analytics['open_rate']; ?>%</div>
                                                                         <div class="metric-label">Open Rate</div>
                                                                     </div>
                                                                 </div>
                            </div>
                        </div>

                        <!-- Charts Row -->
                        <div class="row mb-4">
                            <div class="col-md-6">
                                <div class="card">
                                    <div class="card-header">
                                        <h5 class="mb-0">Emails by Type</h5>
                                    </div>
                                    <div class="card-body">
                                        <div class="chart-container">
                                            <canvas id="typeChart"></canvas>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="card">
                                    <div class="card-header">
                                        <h5 class="mb-0">Daily Email Activity</h5>
                                    </div>
                                    <div class="card-body">
                                        <div class="chart-container">
                                            <canvas id="dailyChart"></canvas>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Recent Emails Table -->
                        <div class="card">
                            <div class="card-header">
                                <h5 class="mb-0">Recent Email Activity</h5>
                            </div>
                            <div class="card-body">
                                <div class="table-responsive">
                                    <table class="table table-hover" id="emailsTable">
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
                                                <td>
                                                    <span class="badge <?php echo $email['opened'] ? 'badge-success' : 'badge-secondary'; ?>">
                                                                                                             <?php echo $email['opened'] ? 'Opened' : 'Sent'; ?>
                                                                                                         </span>
                                                </td>
                                                <td><?php echo $email['opened_at'] ? date('M d, Y H:i', strtotime($email['opened_at'])) : '-'; ?></td>
                                            </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
                        </div> <!-- main-content -->
                    </div> <!-- page-wrapper -->
                </div> <!-- main-body -->
            </div> <!-- pcoded-inner-content -->
        </div> <!-- pcoded-content -->
    </div> <!-- pcoded-main-container -->

<!-- Chart.js -->
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
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