<?php
// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Set secure headers
header("X-XSS-Protection: 1; mode=block");
header("X-Content-Type-Options: nosniff");
header("X-Frame-Options: DENY");
header("Content-Security-Policy: default-src 'self'; script-src 'self' 'unsafe-inline' https://cdn.jsdelivr.net; style-src 'self' 'unsafe-inline' https://fonts.googleapis.com https://cdn.jsdelivr.net; font-src 'self' https://fonts.gstatic.com; img-src 'self' data:;");
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

// Check if user is a super admin
if (!isset($_SESSION['user_id']) || !isset($_SESSION['role']) || $_SESSION['role'] !== 'super_admin' || !is_null($_SESSION['tenant_id'])) {
    error_log("Unauthorized access attempt to SSL monitoring: " . ($_SESSION['user_id'] ?? 'unknown') . " - IP: " . $_SERVER['REMOTE_ADDR']);
    header('Location: ../login.php');
    exit();
}

// Create CSRF token if not exists
if (!isset($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

/**
 * SSL Certificate Monitoring Dashboard
 * For Super Admin - Site Owner SSL Certificate Management
 */

require_once '../includes/db.php';
require_once '../includes/SSLCertificateMonitor.php';

$sslMonitor = new SSLCertificateMonitor($pdo);

// Handle form submissions
$message = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['add_domain'])) {
        // Add new domain
        $domain = trim($_POST['domain'] ?? '');
        $port = (int)($_POST['port'] ?? 443);
        $description = trim($_POST['description'] ?? '');

        if (empty($domain)) {
            $error = 'Domain is required';
        } else {
            $result = $sslMonitor->addDomain($domain, $port, $description);
            if ($result['success']) {
                $message = "Domain '{$domain}' added to monitoring successfully!";
            } else {
                $error = $result['error'];
            }
        }
    } elseif (isset($_POST['remove_domain'])) {
        // Remove domain
        $domainId = (int)$_POST['domain_id'];
        if ($sslMonitor->removeDomain($domainId)) {
            $message = 'Domain removed from monitoring successfully!';
        } else {
            $error = 'Failed to remove domain';
        }
    } elseif (isset($_POST['check_certificates'])) {
        // Manual certificate check
        $results = $sslMonitor->checkAllCertificates();
        $checkedCount = count($results);
        $message = "Checked {$checkedCount} SSL certificate(s) successfully!";
    } elseif (isset($_POST['send_alerts'])) {
        // Send expiry alerts
        $alertsSent = $sslMonitor->sendExpiryAlerts();
        $message = "Sent {$alertsSent} SSL expiry alert(s)!";
    }
}

// Get current SSL certificate data
$certificates = $sslMonitor->getMonitoredDomains();
$attentionNeeded = $sslMonitor->getCertificatesNeedingAttention();
$alertThresholds = $sslMonitor->getAlertThresholds();

include '../includes/header_super_admin.php';
?>

<!-- [ Main Content ] start -->
<div class="pcoded-main-container">
    <div class="pcoded-wrapper">
        <div class="pcoded-content">
            <div class="pcoded-inner-content">
                <!-- [ breadcrumb ] start -->
                <div class="page-header">
                    <div class="page-block">
                        <div class="row align-items-center">
                            <div class="col-md-12">
                                <div class="page-header-title">
                                    <h5 class="page-header-title-text">SSL Certificate Monitoring</h5>
                                </div>
                                <nav aria-label="breadcrumb">
                                    <ol class="breadcrumb">
                                        <li class="breadcrumb-item"><a href="dashboard.php"><i class="feather icon-home"></i></a></li>
                                        <li class="breadcrumb-item active" aria-current="page">SSL Monitoring</li>
                                    </ol>
                                </nav>
                            </div>
                        </div>
                    </div>
                </div>
                <!-- [ breadcrumb ] end -->
                <div class="main-body">
                    <div class="page-wrapper">
                        <!-- [ Main Content ] start -->
                        <div class="main-content">
                            <div class="page-header card">
                                <div class="row align-items-center">
                                    <div class="col-md-6">
                                        <h5 class="mb-0"><i class="fas fa-shield-alt mr-2"></i>SSL Certificate Monitoring</h5>
                                        <p class="mb-0 mt-1" style="font-size: 14px; opacity: 0.9;">Monitor SSL certificate expiry dates and receive alerts</p>
                                    </div>
                                    <div class="col-md-6 text-end">
                                        <button type="button" class="btn btn-primary" data-toggle="modal" data-target="#addDomainModal">
                                            <i class="fas fa-plus mr-1"></i>Add Domain
                                        </button>
                                    </div>
                                </div>
                            </div>

        <!-- Alert Messages -->
        <?php if ($message): ?>
            <div class="alert alert-success alert-dismissible fade show" role="alert">
                <i class="fas fa-check-circle mr-2"></i><?php echo htmlspecialchars($message); ?>
                <button type="button" class="close" data-dismiss="alert">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
        <?php endif; ?>

        <?php if ($error): ?>
            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                <i class="fas fa-exclamation-triangle mr-2"></i><?php echo htmlspecialchars($error); ?>
                <button type="button" class="close" data-dismiss="alert">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
        <?php endif; ?>

        <!-- Alert Thresholds Info -->
        <div class="row mb-4">
            <div class="col-12">
                <div class="card">
                    <div class="card-header bg-light">
                        <h5 class="mb-0"><i class="fas fa-info-circle mr-2"></i>Alert Thresholds</h5>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-3">
                                <div class="text-center">
                                    <div class="badge badge-danger p-2 mb-2" style="font-size: 1.1em;">
                                        <i class="fas fa-exclamation-triangle mr-1"></i>Critical
                                    </div>
                                    <div class="text-muted"><?php echo $alertThresholds['critical']; ?> days</div>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="text-center">
                                    <div class="badge badge-warning p-2 mb-2" style="font-size: 1.1em;">
                                        <i class="fas fa-exclamation-circle mr-1"></i>Warning
                                    </div>
                                    <div class="text-muted"><?php echo $alertThresholds['warning']; ?> days</div>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="text-center">
                                    <div class="badge badge-info p-2 mb-2" style="font-size: 1.1em;">
                                        <i class="fas fa-info-circle mr-1"></i>Info
                                    </div>
                                    <div class="text-muted"><?php echo $alertThresholds['info']; ?> days</div>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="text-center">
                                    <div class="badge badge-success p-2 mb-2" style="font-size: 1.1em;">
                                        <i class="fas fa-check-circle mr-1"></i>OK
                                    </div>
                                    <div class="text-muted">> <?php echo $alertThresholds['info']; ?> days</div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Attention Needed Section -->
        <?php if (!empty($attentionNeeded)): ?>
        <div class="row mb-4">
            <div class="col-12">
                <div class="card border-danger">
                    <div class="card-header bg-danger text-white">
                        <h5 class="mb-0"><i class="fas fa-exclamation-triangle mr-2"></i>Attention Required (<?php echo count($attentionNeeded); ?>)</h5>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-striped">
                                <thead>
                                    <tr>
                                        <th>Domain</th>
                                        <th>Status</th>
                                        <th>Days Left</th>
                                        <th>Expires</th>
                                        <th>Last Checked</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($attentionNeeded as $cert): ?>
                                    <tr>
                                        <td>
                                            <strong><?php echo htmlspecialchars($cert['domain']); ?></strong>
                                            <?php if ($cert['port'] != 443): ?>
                                                <small class="text-muted">(Port: <?php echo $cert['port']; ?>)</small>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <?php
                                            $badgeClass = 'secondary';
                                            $icon = 'question-circle';
                                            switch ($cert['alert_level']) {
                                                case 'expired':
                                                    $badgeClass = 'danger';
                                                    $icon = 'times-circle';
                                                    break;
                                                case 'critical':
                                                    $badgeClass = 'danger';
                                                    $icon = 'exclamation-triangle';
                                                    break;
                                                case 'warning':
                                                    $badgeClass = 'warning';
                                                    $icon = 'exclamation-circle';
                                                    break;
                                                case 'info':
                                                    $badgeClass = 'info';
                                                    $icon = 'info-circle';
                                                    break;
                                            }
                                            ?>
                                            <span class="badge badge-<?php echo $badgeClass; ?>">
                                                <i class="fas fa-<?php echo $icon; ?> mr-1"></i>
                                                <?php echo ucfirst($cert['alert_level']); ?>
                                            </span>
                                        </td>
                                        <td>
                                            <?php if ($cert['is_expired']): ?>
                                                <span class="text-danger font-weight-bold">EXPIRED</span>
                                            <?php elseif ($cert['days_until_expiry'] !== null): ?>
                                                <span class="font-weight-bold"><?php echo $cert['days_until_expiry']; ?> days</span>
                                            <?php else: ?>
                                                <span class="text-muted">Unknown</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <?php echo $cert['valid_to'] ? date('M d, Y', strtotime($cert['valid_to'])) : 'Unknown'; ?>
                                        </td>
                                        <td>
                                            <?php echo $cert['last_checked'] ? date('M d, Y H:i', strtotime($cert['last_checked'])) : 'Never'; ?>
                                        </td>
                                        <td>
                                            <form method="post" style="display: inline;">
                                                <input type="hidden" name="domain_id" value="<?php echo $cert['id']; ?>">
                                                <button type="submit" name="remove_domain" class="btn btn-sm btn-outline-danger"
                                                        onclick="return confirm('Remove <?php echo htmlspecialchars($cert['domain']); ?> from monitoring?')">
                                                    <i class="fas fa-trash"></i>
                                                </button>
                                            </form>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <?php endif; ?>

        <!-- All Certificates Section -->
        <div class="row">
            <div class="col-12">
                <div class="card">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <h5 class="mb-0"><i class="fas fa-globe mr-2"></i>Monitored Domains (<?php echo count($certificates); ?>)</h5>
                        <div>
                            <form method="post" style="display: inline;">
                                <button type="submit" name="check_certificates" class="btn btn-outline-primary btn-sm mr-2">
                                    <i class="fas fa-sync-alt mr-1"></i>Check All
                                </button>
                            </form>
                            <form method="post" style="display: inline;">
                                <button type="submit" name="send_alerts" class="btn btn-outline-warning btn-sm">
                                    <i class="fas fa-bell mr-1"></i>Send Alerts
                                </button>
                            </form>
                        </div>
                    </div>
                    <div class="card-body">
                        <?php if (empty($certificates)): ?>
                            <div class="text-center py-5">
                                <i class="fas fa-globe text-muted" style="font-size: 48px;"></i>
                                <h5 class="mt-3">No domains configured</h5>
                                <p class="text-muted">Add your first domain to start monitoring SSL certificates.</p>
                                <button type="button" class="btn btn-primary" data-toggle="modal" data-target="#addDomainModal">
                                    <i class="fas fa-plus mr-1"></i>Add Domain
                                </button>
                            </div>
                        <?php else: ?>
                            <div class="table-responsive">
                                <table class="table table-striped">
                                    <thead>
                                        <tr>
                                            <th>Domain</th>
                                            <th>Status</th>
                                            <th>Issuer</th>
                                            <th>Valid Until</th>
                                            <th>Days Left</th>
                                            <th>Last Checked</th>
                                            <th>Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($certificates as $cert): ?>
                                        <tr>
                                            <td>
                                                <strong><?php echo htmlspecialchars($cert['domain']); ?></strong>
                                                <?php if ($cert['port'] != 443): ?>
                                                    <small class="text-muted">(Port: <?php echo $cert['port']; ?>)</small>
                                                <?php endif; ?>
                                                <?php if ($cert['description']): ?>
                                                    <br><small class="text-muted"><?php echo htmlspecialchars($cert['description']); ?></small>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <?php if ($cert['last_checked'] === null): ?>
                                                    <span class="badge badge-secondary">
                                                        <i class="fas fa-question-circle mr-1"></i>Not Checked
                                                    </span>
                                                <?php elseif (!$cert['is_valid']): ?>
                                                    <span class="badge badge-danger">
                                                        <i class="fas fa-times-circle mr-1"></i>Invalid
                                                    </span>
                                                <?php else: ?>
                                                    <?php
                                                    $badgeClass = 'secondary';
                                                    $icon = 'question-circle';
                                                    switch ($cert['alert_level']) {
                                                        case 'expired':
                                                            $badgeClass = 'danger';
                                                            $icon = 'times-circle';
                                                            break;
                                                        case 'critical':
                                                            $badgeClass = 'danger';
                                                            $icon = 'exclamation-triangle';
                                                            break;
                                                        case 'warning':
                                                            $badgeClass = 'warning';
                                                            $icon = 'exclamation-circle';
                                                            break;
                                                        case 'info':
                                                            $badgeClass = 'info';
                                                            $icon = 'info-circle';
                                                            break;
                                                        case 'ok':
                                                            $badgeClass = 'success';
                                                            $icon = 'check-circle';
                                                            break;
                                                    }
                                                    ?>
                                                    <span class="badge badge-<?php echo $badgeClass; ?>">
                                                        <i class="fas fa-<?php echo $icon; ?> mr-1"></i>
                                                        <?php echo ucfirst($cert['alert_level']); ?>
                                                    </span>
                                                <?php endif; ?>
                                            </td>
                                            <td><?php echo htmlspecialchars($cert['issuer'] ?? 'Unknown'); ?></td>
                                            <td>
                                                <?php if ($cert['valid_to']): ?>
                                                    <?php echo date('M d, Y', strtotime($cert['valid_to'])); ?>
                                                    <br><small class="text-muted"><?php echo date('H:i', strtotime($cert['valid_to'])); ?></small>
                                                <?php else: ?>
                                                    <span class="text-muted">Unknown</span>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <?php if ($cert['is_expired']): ?>
                                                    <span class="text-danger font-weight-bold">EXPIRED</span>
                                                <?php elseif ($cert['days_until_expiry'] !== null): ?>
                                                    <span class="font-weight-bold"><?php echo $cert['days_until_expiry']; ?> days</span>
                                                <?php else: ?>
                                                    <span class="text-muted">-</span>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <?php if ($cert['last_checked']): ?>
                                                    <?php echo date('M d, Y H:i', strtotime($cert['last_checked'])); ?>
                                                <?php else: ?>
                                                    <span class="text-muted">Never</span>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <form method="post" style="display: inline;">
                                                    <input type="hidden" name="domain_id" value="<?php echo $cert['id']; ?>">
                                                    <button type="submit" name="remove_domain" class="btn btn-sm btn-outline-danger"
                                                            onclick="return confirm('Remove <?php echo htmlspecialchars($cert['domain']); ?> from monitoring?')">
                                                        <i class="fas fa-trash"></i>
                                                    </button>
                                                </form>
                                            </td>
                                        </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
                    </div>
                    <!-- [ Main Content ] end -->
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Add Domain Modal -->
<div class="modal fade" id="addDomainModal" tabindex="-1" role="dialog" aria-labelledby="addDomainModalLabel" aria-hidden="true">
    <div class="modal-dialog" role="document">
        <div class="modal-content">
            <form method="post">
                <div class="modal-header">
                    <h5 class="modal-title" id="addDomainModalLabel">
                        <i class="fas fa-plus mr-2"></i>Add Domain to Monitor
                    </h5>
                    <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                <div class="modal-body">
                    <div class="form-group">
                        <label for="domain">Domain Name <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" id="domain" name="domain"
                               placeholder="example.com" required>
                        <small class="form-text text-muted">
                            Enter the domain name without https:// (e.g., almoqadas.com)
                        </small>
                    </div>
                    <div class="form-group">
                        <label for="port">Port</label>
                        <input type="number" class="form-control" id="port" name="port"
                               value="443" min="1" max="65535">
                        <small class="form-text text-muted">
                            Default SSL port is 443. Change only if using a different port.
                        </small>
                    </div>
                    <div class="form-group">
                        <label for="description">Description (Optional)</label>
                        <input type="text" class="form-control" id="description" name="description"
                               placeholder="Main website domain">
                        <small class="form-text text-muted">
                            Add a description to help identify this domain.
                        </small>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
                    <button type="submit" name="add_domain" class="btn btn-primary">
                        <i class="fas fa-plus mr-1"></i>Add Domain
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php include '../includes/admin_footer.php'; ?>

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

.table-responsive {
    border-radius: 10px;
    overflow: hidden;
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

<!-- Required Js -->
<script src="../assets/js/vendor-all.min.js"></script>
<script src="../assets/plugins/bootstrap/js/bootstrap.min.js"></script>
<script src="../assets/js/pcoded.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
</body>
</html>