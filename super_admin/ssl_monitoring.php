<?php
// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

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
            if ($result['success'] ?? false) {
                $message = "Domain '{$domain}' added to monitoring successfully!";
            } else {
                $error = $result['error'] ?? 'Failed to add domain';
            }
        }
    } elseif (isset($_POST['remove_domain'])) {
        // Remove domain
        $domainId = (int)($_POST['domain_id'] ?? 0);
        if ($sslMonitor->removeDomain($domainId)) {
            $message = 'Domain removed from monitoring successfully!';
        } else {
            $error = 'Failed to remove domain';
        }
    } elseif (isset($_POST['check_certificates'])) {
        // Manual certificate check
        $results = $sslMonitor->checkAllCertificates();
        $checkedCount = is_array($results) ? count($results) : 0;
        $message = "Checked {$checkedCount} SSL certificate(s) successfully!";
    } elseif (isset($_POST['send_alerts'])) {
        // Send expiry alerts
        $alertsSent = $sslMonitor->sendExpiryAlerts() ?? 0;
        $message = "Sent {$alertsSent} SSL expiry alert(s)!";
    }
}

// Get current SSL certificate data
$certificates = $sslMonitor->getMonitoredDomains() ?? [];
$attentionNeeded = $sslMonitor->getCertificatesNeedingAttention() ?? [];
$alertThresholds = $sslMonitor->getAlertThresholds() ?? ['critical' => 7, 'warning' => 30, 'info' => 60];

include '../includes/header_super_admin.php';
?>

<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Sora:wght@300;400;500;600;700&family=JetBrains+Mono:wght@400;500&display=swap" rel="stylesheet">
<style>
/* ─── TOKENS ─────────────────────────────────────────────── */
:root {
  --bg:       #f8fafc;
  --surface:  #ffffff;
  --surface2: #f1f5f9;
  --border:   #e5e7eb;
  --text:     #1f2937;
  --muted:    #6b7280;
  --accent:   #4099ff;
  --accent2:  #2ed8b6;
  --green:    #10b981;
  --amber:    #f59e0b;
  --red:      #ef4444;
  --blue:     #3b82f6;
  --purple:   #8b5cf6;
  --orange:   #f97316;
  --radius:   14px;
}

/* ─── RESET / BASE ───────────────────────────────────────── */
*, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
html { font-size: 14px; }
body {
  font-family: 'Sora', sans-serif;
  background: var(--bg);
  color: var(--text);
  min-height: 100vh;
}

/* ─── MAIN WRAPPER ───────────────────────────────────────── */
.sa-wrap { display: flex; flex-direction: column; min-height: 100vh; }

/* ─── CONTENT ────────────────────────────────────────────── */
.sa-content { 
    padding: 24px 28px; 
    display: flex; 
    flex-direction: column; 
    gap: 24px; 
}

/* ─── CARD ───────────────────────────────────────────────── */
.sa-card {
  background: var(--surface); 
  border: 1px solid var(--border);
  border-left: 4px solid var(--accent);
  border-radius: var(--radius); 
  overflow: hidden;
  transition: all .2s;
  box-shadow: 0 1px 3px rgba(0,0,0,0.04);
  margin-bottom: 24px;
}
.sa-card:last-child { margin-bottom: 0; }
.sa-card:hover { 
    border-left-color: var(--accent2);
    transform: translateY(-2px);
    box-shadow: 0 4px 12px rgba(0,0,0,0.08);
}
.sa-card-hdr {
  padding: 16px 24px; 
  border-bottom: 1px solid var(--border);
  display: flex; 
  align-items: center; 
  justify-content: space-between;
  background: linear-gradient(135deg, rgba(108,99,255,0.04), rgba(46,216,182,0.02));
}
.sa-card-hdr h3 { 
    font-size: .95rem; 
    font-weight: 600; 
    color: var(--text);
    display: flex;
    align-items: center;
    letter-spacing: -0.01em;
}
.sa-card-body { 
    padding: 24px; 
}

/* Card colors */
.sa-card.alert-card { border-left-color: var(--red); }
.sa-card.success-card { border-left-color: var(--green); }
.sa-card.warning-card { border-left-color: var(--amber); }
.sa-card.info-card { border-left-color: var(--blue); }

/* ─── BUTTON ─────────────────────────────────────────────── */
.sa-btn {
  font-size: .8rem; font-weight: 600; font-family: 'Sora', sans-serif;
  padding: 8px 16px; border-radius: 20px; cursor: pointer; border: none;
  display: inline-flex; align-items: center; gap: 6px; text-decoration: none;
  transition: all .15s;
}
.sa-btn-primary {
  background: linear-gradient(135deg, var(--accent), var(--accent2)); color: #fff;
}
.sa-btn-primary:hover { opacity: .85; transform: translateY(-1px); }
.sa-btn-ghost {
  background: var(--surface2); color: var(--muted); border: 1px solid var(--border);
}
.sa-btn-ghost:hover { color: var(--text); border-color: var(--accent); }
.sa-btn-danger {
  background: linear-gradient(135deg, var(--red), #dc2626); color: #fff;
}
.sa-btn-warning {
  background: linear-gradient(135deg, var(--amber), #fbbf24); color: #fff;
}

/* ─── STATS GRID ─────────────────────────────────────────── */
.threshold-grid {
    display: grid;
    grid-template-columns: repeat(4, 1fr);
    gap: 16px;
}
.threshold-item {
    text-align: center;
    padding: 16px;
    border-radius: 10px;
    background: var(--surface2);
}
.threshold-item.critical { border-left: 3px solid var(--red); }
.threshold-item.warning { border-left: 3px solid var(--amber); }
.threshold-item.info { border-left: 3px solid var(--blue); }
.threshold-item.ok { border-left: 3px solid var(--green); }
.threshold-number {
    font-size: 1.5rem;
    font-weight: 700;
    font-family: 'JetBrains Mono', monospace;
}
.threshold-label {
    font-size: 0.75rem;
    color: var(--muted);
    text-transform: uppercase;
    margin-top: 4px;
}

/* ─── TABLE STYLES ──────────────────────────────────────── */
.table-wrapper {
    overflow-x: auto;
    border-radius: 10px;
}
.sa-table {
    width: 100%;
    border-collapse: collapse;
}
.sa-table th {
    background: var(--surface2);
    padding: 12px 16px;
    text-align: left;
    font-weight: 600;
    color: var(--muted);
    font-size: 0.75rem;
    text-transform: uppercase;
    border-bottom: 2px solid var(--border);
}
.sa-table td {
    padding: 14px 16px;
    border-bottom: 1px solid var(--border);
    vertical-align: middle;
}
.sa-table tr:hover {
    background: rgba(108,99,255,.02);
}
.sa-table tr:last-child td {
    border-bottom: none;
}

/* ─── BADGES ─────────────────────────────────────────────── */
.badge-custom {
    font-size: 0.7rem;
    font-weight: 600;
    padding: 4px 10px;
    border-radius: 20px;
    text-transform: uppercase;
    letter-spacing: 0.03em;
}
.badge-expired { background: rgba(239,68,68,.15); color: var(--red); }
.badge-critical { background: rgba(239,68,68,.15); color: var(--red); }
.badge-warning { background: rgba(245,158,11,.15); color: var(--amber); }
.badge-info { background: rgba(59,130,246,.15); color: var(--blue); }
.badge-ok { background: rgba(16,185,129,.15); color: var(--green); }
.badge-unknown { background: rgba(107,114,128,.15); color: var(--muted); }
.badge-valid { background: rgba(16,185,129,.15); color: var(--green); }
.badge-invalid { background: rgba(239,68,68,.15); color: var(--red); }
.badge-not-checked { background: rgba(107,114,128,.15); color: var(--muted); }

/* ─── ALERT BOX ─────────────────────────────────────────── */
.alert-box {
    padding: 14px 18px;
    border-radius: 10px;
    margin-bottom: 20px;
    font-size: 0.85rem;
    display: flex;
    align-items: center;
    gap: 10px;
}
.alert-box.success {
    background: rgba(16,185,129,.1);
    border: 1px solid rgba(16,185,129,.3);
    color: var(--green);
}
.alert-box.danger {
    background: rgba(239,68,68,.1);
    border: 1px solid rgba(239,68,68,.3);
    color: var(--red);
}

/* ─── EMPTY STATE ───────────────────────────────────────── */
.empty-state {
    text-align: center;
    padding: 48px 24px;
    color: var(--muted);
}
.empty-state-icon {
    font-size: 3rem;
    margin-bottom: 12px;
    opacity: 0.5;
}

/* ─── FORM STYLES ───────────────────────────────────────── */
.form-group { margin-bottom: 16px; }

.form-label {
    display: block;
    font-weight: 600;
    color: var(--text);
    margin-bottom: 6px;
    font-size: 0.8rem;
}

.form-control {
    width: 100%;
    padding: 10px 14px;
    border: 1px solid var(--border);
    border-radius: 8px;
    font-size: 0.85rem;
    transition: all .15s ease;
    background: var(--surface2);
    color: var(--text);
    font-family: 'Sora', sans-serif;
}

.form-control:focus {
    outline: none;
    border-color: var(--accent);
    box-shadow: 0 0 0 3px rgba(108,99,255,.15);
    background: var(--surface);
}

/* ─── PCODED LAYOUT INTEGRATION ──────────────────────────── */
body { background: var(--bg) !important; }
.pcoded-main-container, .pcoded-wrapper, .pcoded-content, .pcoded-inner-content { background: var(--bg) !important; }
.page-header { background: transparent !important; border: none !important; box-shadow: none !important; }
.page-header h5 { color: var(--text) !important; }
.breadcrumb { background: transparent !important; }
.breadcrumb-item a, .breadcrumb-item.active { color: var(--muted) !important; }

/* ─── RESPONSIVE ─────────────────────────────────────────── */
@media (max-width: 768px) {
    .sa-content { padding: 16px; }
    .threshold-grid { grid-template-columns: repeat(2, 1fr); }
    .table-wrapper { font-size: 0.85rem; }
    .sa-table th, .sa-table td { padding: 10px 12px; }
}
</style>

<div class="pcoded-main-container">
    <div class="pcoded-content">
        <div class="page-header">
            <div class="page-block">
                <div class="row align-items-center">
                    <div class="col-md-12">
                        <div class="page-header-title">
                            <h5 class="m-b-10">SSL Certificate Monitoring</h5>
                        </div>
                        <ul class="breadcrumb">
                            <li class="breadcrumb-item"><a href="dashboard.php"><i class="feather icon-home"></i></a></li>
                            <li class="breadcrumb-item active">SSL Monitoring</li>
                        </ul>
                    </div>
                </div>
            </div>
        </div>

        <div class="sa-wrap">
            <div class="sa-content">

                <!-- Alert Messages -->
                <?php if (!empty($message)): ?>
                    <div class="alert-box success">
                        <i class="feather icon-check-circle"></i>
                        <?= htmlspecialchars($message) ?>
                    </div>
                <?php endif; ?>

                <?php if (!empty($error)): ?>
                    <div class="alert-box danger">
                        <i class="feather icon-alert-circle"></i>
                        <?= htmlspecialchars($error) ?>
                    </div>
                <?php endif; ?>

                <!-- Header Card -->
                <div class="sa-card" style="border-left-color: #6366f1;">
                    <div class="sa-card-hdr">
                        <h3><i class="feather icon-shield" style="margin-right:8px"></i>SSL Certificate Monitoring</h3>
                        <button type="button" class="sa-btn sa-btn-primary" data-toggle="modal" data-target="#addDomainModal">
                            <i class="feather icon-plus"></i>Add Domain
                        </button>
                    </div>
                    <div class="sa-card-body">
                        <p style="color: var(--muted); margin: 0;">Monitor SSL certificate expiry dates and receive alerts</p>
                    </div>
                </div>

                <!-- Alert Thresholds Card -->
                <div class="sa-card">
                    <div class="sa-card-hdr">
                        <h3><i class="feather icon-info" style="margin-right:8px"></i>Alert Thresholds</h3>
                    </div>
                    <div class="sa-card-body">
                        <div class="threshold-grid">
                            <div class="threshold-item critical">
                                <div class="threshold-number" style="color: var(--red);"><?= $alertThresholds['critical'] ?? 7 ?></div>
                                <div class="threshold-label">Critical Days</div>
                            </div>
                            <div class="threshold-item warning">
                                <div class="threshold-number" style="color: var(--amber);"><?= $alertThresholds['warning'] ?? 30 ?></div>
                                <div class="threshold-label">Warning Days</div>
                            </div>
                            <div class="threshold-item info">
                                <div class="threshold-number" style="color: var(--blue);"><?= $alertThresholds['info'] ?? 60 ?></div>
                                <div class="threshold-label">Info Days</div>
                            </div>
                            <div class="threshold-item ok">
                                <div class="threshold-number" style="color: var(--green);">OK</div>
                                <div class="threshold-label">Above Info</div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Attention Needed Section -->
                <?php if (!empty($attentionNeeded)): ?>
                <div class="sa-card alert-card">
                    <div class="sa-card-hdr">
                        <h3><i class="feather icon-alert-triangle" style="margin-right:8px"></i>Attention Required (<?= count($attentionNeeded) ?>)</h3>
                    </div>
                    <div class="sa-card-body" style="padding: 0;">
                        <div class="table-wrapper">
                            <table class="sa-table">
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
                                            <strong><?= htmlspecialchars($cert['domain'] ?? 'Unknown') ?></strong>
                                            <?php if (($cert['port'] ?? 443) != 443): ?>
                                                <small class="text-muted">(Port: <?= $cert['port'] ?>)</small>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <?php 
                                            $alertLevel = $cert['alert_level'] ?? 'unknown';
                                            $badgeClass = match($alertLevel) {
                                                'expired', 'critical' => 'badge-critical',
                                                'warning' => 'badge-warning',
                                                'info' => 'badge-info',
                                                default => 'badge-unknown'
                                            };
                                            ?>
                                            <span class="badge-custom <?= $badgeClass ?>"><?= ucfirst($alertLevel) ?></span>
                                        </td>
                                        <td>
                                            <?php if ($cert['is_expired'] ?? false): ?>
                                                <span style="color: var(--red); font-weight: 600;">EXPIRED</span>
                                            <?php elseif (isset($cert['days_until_expiry'])): ?>
                                                <span style="font-weight: 600;"><?= $cert['days_until_expiry'] ?> days</span>
                                            <?php else: ?>
                                                <span style="color: var(--muted);">Unknown</span>
                                            <?php endif; ?>
                                        </td>
                                        <td><?= $cert['valid_to'] ? date('M d, Y', strtotime($cert['valid_to'])) : 'Unknown' ?></td>
                                        <td><?= $cert['last_checked'] ? date('M d, Y H:i', strtotime($cert['last_checked'])) : 'Never' ?></td>
                                        <td>
                                            <form method="post" style="display: inline;">
                                                <input type="hidden" name="domain_id" value="<?= $cert['id'] ?? 0 ?>">
                                                <button type="submit" name="remove_domain" class="sa-btn sa-btn-danger" style="padding: 4px 10px;"
                                                        onclick="return confirm('Remove <?= htmlspecialchars($cert['domain'] ?? '') ?>?')">
                                                    <i class="feather icon-trash-2"></i>
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
                <?php endif; ?>

                <!-- All Certificates Card -->
                <div class="sa-card">
                    <div class="sa-card-hdr">
                        <h3><i class="feather icon-globe" style="margin-right:8px"></i>Monitored Domains (<?= count($certificates) ?>)</h3>
                        <div style="display: flex; gap: 8px;">
                            <form method="post">
                                <button type="submit" name="check_certificates" class="sa-btn sa-btn-ghost">
                                    <i class="feather icon-refresh-cw"></i>Check All
                                </button>
                            </form>
                            <form method="post">
                                <button type="submit" name="send_alerts" class="sa-btn sa-btn-warning">
                                    <i class="feather icon-bell"></i>Send Alerts
                                </button>
                            </form>
                        </div>
                    </div>
                    <div class="sa-card-body" style="padding: <?= empty($certificates) ? '0' : '0 0 0 0' ?>;">
                        <?php if (empty($certificates)): ?>
                            <div class="empty-state">
                                <div class="empty-state-icon"><i class="feather icon-globe"></i></div>
                                <div>No domains configured</div>
                                <p style="margin-top: 8px; font-size: 0.85rem;">Add your first domain to start monitoring SSL certificates.</p>
                                <button type="button" class="sa-btn sa-btn-primary" style="margin-top: 16px;" data-toggle="modal" data-target="#addDomainModal">
                                    <i class="feather icon-plus"></i>Add Domain
                                </button>
                            </div>
                        <?php else: ?>
                            <div class="table-wrapper">
                                <table class="sa-table">
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
                                                <strong><?= htmlspecialchars($cert['domain'] ?? 'Unknown') ?></strong>
                                                <?php if (($cert['port'] ?? 443) != 443): ?>
                                                    <small class="text-muted">(Port: <?= $cert['port'] ?>)</small>
                                                <?php endif; ?>
                                                <?php if (!empty($cert['description'])): ?>
                                                    <br><small class="text-muted"><?= htmlspecialchars($cert['description']) ?></small>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <?php if ($cert['last_checked'] === null): ?>
                                                    <span class="badge-custom badge-not-checked">Not Checked</span>
                                                <?php elseif (!($cert['is_valid'] ?? true)): ?>
                                                    <span class="badge-custom badge-invalid">Invalid</span>
                                                <?php else: ?>
                                                    <?php 
                                                    $alertLevel = $cert['alert_level'] ?? 'unknown';
                                                    $badgeClass = match($alertLevel) {
                                                        'expired', 'critical' => 'badge-critical',
                                                        'warning' => 'badge-warning',
                                                        'info' => 'badge-info',
                                                        'ok' => 'badge-ok',
                                                        default => 'badge-unknown'
                                                    };
                                                    ?>
                                                    <span class="badge-custom <?= $badgeClass ?>"><?= ucfirst($alertLevel) ?></span>
                                                <?php endif; ?>
                                            </td>
                                            <td><?= htmlspecialchars($cert['issuer'] ?? 'Unknown') ?></td>
                                            <td>
                                                <?php if (!empty($cert['valid_to'])): ?>
                                                    <?= date('M d, Y', strtotime($cert['valid_to'])) ?>
                                                    <br><small class="text-muted"><?= date('H:i', strtotime($cert['valid_to'])) ?></small>
                                                <?php else: ?>
                                                    <span style="color: var(--muted);">Unknown</span>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <?php if ($cert['is_expired'] ?? false): ?>
                                                    <span style="color: var(--red); font-weight: 600;">EXPIRED</span>
                                                <?php elseif (isset($cert['days_until_expiry'])): ?>
                                                    <span style="font-weight: 600;"><?= $cert['days_until_expiry'] ?> days</span>
                                                <?php else: ?>
                                                    <span style="color: var(--muted);">-</span>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <?php if (!empty($cert['last_checked'])): ?>
                                                    <?= date('M d, Y H:i', strtotime($cert['last_checked'])) ?>
                                                <?php else: ?>
                                                    <span style="color: var(--muted);">Never</span>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <form method="post" style="display: inline;">
                                                    <input type="hidden" name="domain_id" value="<?= $cert['id'] ?? 0 ?>">
                                                    <button type="submit" name="remove_domain" class="sa-btn sa-btn-danger" style="padding: 4px 10px;"
                                                            onclick="return confirm('Remove <?= htmlspecialchars($cert['domain'] ?? '') ?>?')">
                                                        <i class="feather icon-trash-2"></i>
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
</div>

<!-- Add Domain Modal -->
<div class="modal fade" id="addDomainModal" tabindex="-1" role="dialog" aria-labelledby="addDomainModalLabel" aria-hidden="true">
    <div class="modal-dialog" role="document">
        <div class="modal-content" style="border-radius: 14px; overflow: hidden;">
            <form method="post">
                <div class="modal-header" style="background: linear-gradient(135deg, #6366f1, #8b5cf6); color: white; border: none;">
                    <h5 class="modal-title" id="addDomainModalLabel" style="display: flex; align-items: center; gap: 8px;">
                        <i class="feather icon-plus-circle"></i>Add Domain to Monitor
                    </h5>
                    <button type="button" class="close" data-dismiss="modal" aria-label="Close" style="color: white; opacity: 0.8;">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                <div class="modal-body" style="padding: 24px;">
                    <div class="form-group">
                        <label class="form-label" for="domain">Domain Name <span style="color: var(--red);">*</span></label>
                        <input type="text" class="form-control" id="domain" name="domain" placeholder="example.com" required>
                        <small style="color: var(--muted); font-size: 0.75rem; margin-top: 4px; display: block;">
                            Enter the domain name without https:// (e.g., almoqadas.com)
                        </small>
                    </div>
                    <div class="form-group">
                        <label class="form-label" for="port">Port</label>
                        <input type="number" class="form-control" id="port" name="port" value="443" min="1" max="65535">
                        <small style="color: var(--muted); font-size: 0.75rem; margin-top: 4px; display: block;">
                            Default SSL port is 443. Change only if using a different port.
                        </small>
                    </div>
                    <div class="form-group">
                        <label class="form-label" for="description">Description (Optional)</label>
                        <input type="text" class="form-control" id="description" name="description" placeholder="Main website domain">
                        <small style="color: var(--muted); font-size: 0.75rem; margin-top: 4px; display: block;">
                            Add a description to help identify this domain.
                        </small>
                    </div>
                </div>
                <div class="modal-footer" style="border-top: 1px solid var(--border); padding: 16px 24px;">
                    <button type="button" class="sa-btn sa-btn-ghost" data-dismiss="modal">Cancel</button>
                    <button type="submit" name="add_domain" class="sa-btn sa-btn-primary">
                        <i class="feather icon-plus"></i>Add Domain
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
    <!-- Required Js -->
    <script src="../assets/js/vendor-all.min.js"></script>
    <script src="../assets/plugins/bootstrap/js/bootstrap.min.js"></script>
    <script src="../assets/js/pcoded.min.js"></script>
<?php include '../includes/admin_footer.php'; ?>
