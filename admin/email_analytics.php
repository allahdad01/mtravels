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

require_once '../includes/conn.php';
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
function getEmailAnalytics($conn, $tenant_id, $branch_id, $startDate, $endDate, $emailType) {
   $whereClause = "WHERE et.tenant_id = ? AND et.branch_id = ? AND DATE(et.sent_at) BETWEEN ? AND ?";
   $params = [$tenant_id, $branch_id, $startDate, $endDate];
   $types = "iiss";

    if ($emailType !== 'all') {
        $whereClause .= " AND et.email_type = ?";
        $params[] = $emailType;
        $types .= "s";
    }

    // Total emails sent
    $stmt = $conn->prepare("SELECT COUNT(*) as total_sent FROM email_tracking et $whereClause");
    $stmt->bind_param($types, ...$params);
    $stmt->execute();
    $totalSent = $stmt->get_result()->fetch_assoc()['total_sent'];
    $stmt->close();

    // Total emails opened
    $stmt = $conn->prepare("SELECT COUNT(*) as total_opened FROM email_tracking et $whereClause AND et.opened = 1");
    $stmt->bind_param($types, ...$params);
    $stmt->execute();
    $totalOpened = $stmt->get_result()->fetch_assoc()['total_opened'];
    $stmt->close();

    // Open rate
    $openRate = $totalSent > 0 ? round(($totalOpened / $totalSent) * 100, 2) : 0;

    // Emails by type
    $stmt = $conn->prepare("SELECT et.email_type, COUNT(*) as count, SUM(et.opened) as opened FROM email_tracking et $whereClause GROUP BY et.email_type ORDER BY count DESC");
    $stmt->bind_param($types, ...$params);
    $stmt->execute();
    $emailsByType = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();

    // Daily email stats
    $stmt = $conn->prepare("SELECT DATE(et.sent_at) as date, COUNT(*) as sent, SUM(et.opened) as opened FROM email_tracking et $whereClause GROUP BY DATE(et.sent_at) ORDER BY date DESC");
    $stmt->bind_param($types, ...$params);
    $stmt->execute();
    $dailyStats = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();

    // Recent emails
    $stmt = $conn->prepare("SELECT et.*, DATE(et.sent_at) as sent_date FROM email_tracking et $whereClause ORDER BY et.sent_at DESC LIMIT 50");
    $stmt->bind_param($types, ...$params);
    $stmt->execute();
    $recentEmails = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();

    return [
        'total_sent' => $totalSent,
        'total_opened' => $totalOpened,
        'open_rate' => $openRate,
        'emails_by_type' => $emailsByType,
        'daily_stats' => $dailyStats,
        'recent_emails' => $recentEmails
    ];
}

$analytics = getEmailAnalytics($conn, $tenant_id, $branch_id, $startDate, $endDate, $emailType);

include '../includes/header.php';
?>

<style>
.analytics-card {
    border: none;
    border-radius: 10px;
    box-shadow: 0 2px 10px rgba(0,0,0,0.1);
    transition: transform 0.2s;
}

.analytics-card:hover {
    transform: translateY(-2px);
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

.table-responsive {
    border-radius: 10px;
    overflow: hidden;
}

.status-badge {
    padding: 0.25rem 0.5rem;
    border-radius: 20px;
    font-size: 0.75rem;
    font-weight: 500;
}

.status-opened {
    background-color: rgba(40, 167, 69, 0.1);
    color: #28a745;
}

.status-not-opened {
    background-color: rgba(108, 117, 125, 0.1);
    color: #6c757d;
}

.filter-section {
    background: #f8f9fa;
    padding: 1.5rem;
    border-radius: 10px;
    margin-bottom: 2rem;
}
</style>
    <!-- [ Main Content ] start -->
    <div class="pcoded-main-container">
        <div class="pcoded-content">
            <!-- [ breadcrumb ] start -->
            <div class="container-fluid">
                <div class="row">
                    <div class="col-12">
                        <div class="d-flex justify-content-between align-items-center mb-4">
                            <h2><i class="feather icon-mail mr-2"></i>Email Analytics</h2>
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
                                <div class="card analytics-card">
                                    <div class="card-body text-center">
                                        <div class="metric-value"><?php echo number_format($analytics['total_sent']); ?></div>
                                        <div class="metric-label">Total Emails Sent</div>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="card analytics-card">
                                    <div class="card-body text-center">
                                        <div class="metric-value"><?php echo number_format($analytics['total_opened']); ?></div>
                                        <div class="metric-label">Total Emails Opened</div>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="card analytics-card">
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
                                <div class="card analytics-card">
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
                                <div class="card analytics-card">
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
                        <div class="card analytics-card">
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
                                                    <span class="status-badge <?php echo $email['opened'] ? 'status-opened' : 'status-not-opened'; ?>">
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
            </div>
            </div>           

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

// Initialize DataTable
$(document).ready(function() {
    $('#emailsTable').DataTable({
        responsive: true,
        pageLength: 25,
        order: [[0, 'desc']],
        language: {
            search: "Search emails:",
            lengthMenu: "Show _MENU_ emails per page",
            info: "Showing _START_ to _END_ of _TOTAL_ emails"
        }
    });
});
</script>

<?php include '../includes/admin_footer.php'; ?>